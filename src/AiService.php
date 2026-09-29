<?php
declare(strict_types=1);

final class AiService
{
    public function __construct(
        private GeminiClient $client,
        private GetmoreConnector $connector,
        private AssistantRepository $repository
    ) {
    }

    public function reply(
        string $message,
        array $history = [],
        string $widgetKey = '',
        int $assistantId = 1
    ): string {
        $assistant = null;
        if ($widgetKey !== '') {
            $assistant = $this->repository->getByWidgetKey($widgetKey);
            if (!$assistant) {
                return 'This assistant is currently unavailable.';
            }
        } else {
            $assistant = $this->repository->getAssistant($assistantId);
        }

        if (!$assistant || !(bool) ($assistant['enabled'] ?? 0)) {
            return 'This assistant is currently unavailable.';
        }

        if (isset($assistant['institute_active']) && !(bool) $assistant['institute_active']) {
            return 'This assistant is currently unavailable.';
        }

        $instituteId = (int) ($assistant['institute_id'] ?? 1);
        $assistantId = (int) $assistant['id'];

        $permissions = $this->repository->getPermissions($assistantId);
        $tools = $this->buildTools($permissions);
        $contents = $this->buildContents($history, $message);

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $this->buildInstructions($assistant, $permissions)],
                ],
            ],
            'contents' => $contents,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        /*
         * Gemini can request one or more functions.
         * We allow up to 4 tool rounds for one user query.
         */
        for ($round = 0; $round < 4; $round++) {
            $response = $this->client->generateContent($payload);

            $candidate = $response['candidates'][0] ?? null;
            if (!is_array($candidate) || !isset($candidate['content'])) {
                return 'I could not produce a response. Please try again.';
            }

            $modelContent = $candidate['content'];
            $calls = $this->extractFunctionCalls($modelContent);

            /*
             * No tool call means Gemini has produced the final answer.
             */
            if ($calls === []) {
                $text = $this->extractText($modelContent);

                return $text !== ''
                    ? $text
                    : 'I could not produce a response. Please try again.';
            }

            /*
             * Prepare modelContent for appending to conversation history.
             * In PHP, json_decode transforms empty JSON objects `{}` into `[]`.
             * Gemini API rejects `args: []` with:
             * "Proto field is not repeating, cannot start list."
             * We must normalize any empty `args` to `new stdClass()`.
             */
            if (isset($modelContent['parts']) && is_array($modelContent['parts'])) {
                foreach ($modelContent['parts'] as &$part) {
                    if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                        if (empty($part['functionCall']['args'])) {
                            $part['functionCall']['args'] = new stdClass();
                        }
                    }
                }
                unset($part);
            }

            $payload['contents'][] = $modelContent;

            $functionResponseParts = [];
            foreach ($calls as $call) {
                $result = $this->executeTool($call, $permissions, $instituteId);

                $respPart = [
                    'functionResponse' => [
                        'name' => $call['name'],
                        'response' => [
                            'result' => $result,
                        ],
                    ],
                ];

                if ($call['call_id'] !== '') {
                    $respPart['functionResponse']['id'] = $call['call_id'];
                }

                $functionResponseParts[] = $respPart;
            }

            $payload['contents'][] = [
                'role' => 'user',
                'parts' => $functionResponseParts,
            ];
        }

        return 'I could not complete that request. Please try again.';
    }

    private function buildInstructions(array $assistant, array $permissions): string
    {
        $assistantName = (string) ($assistant['name'] ?? 'GETMORE AI');
        $instituteName = (string) ($assistant['institute_name'] ?? 'our institute');
        $description = (string) ($assistant['description'] ?? '');
        $purpose = (string) ($assistant['purpose'] ?? '');

        $instDetailsEnabled = !empty($permissions['institute_details']['enabled']);
        $classEnabled = !empty($permissions['class_details']['enabled']);
        $teacherEnabled = !empty($permissions['teacher_details']['enabled']);
        $newCoursesEnabled = !empty($permissions['new_courses']['enabled']);
        $attendanceEnabled = !empty($permissions['attendance_details']['enabled']);

        // 1. Institute Info Instructions
        $instSection = $instDetailsEnabled
            ? "- When a visitor asks about the institute (e.g., 'Tell me about this institute', location, address, phone, email, website, opening hours, facilities, registration info): Call `get_public_institute_details`."
            : "- Institute details lookup is disabled by the administrator. If asked about the institute, state that institute details are currently unavailable.";

        // 2. Class & Course Search Instructions
        $classSection = '';
        if ($classEnabled) {
            $classSection = <<<CLASSES_ENABLED
CLASS & COURSE INFORMATION:
- For general questions about what classes or courses are offered (e.g. 'What classes do you have?', 'What courses are available?', 'Show your classes'):
  Call `get_public_classes`.
- For specific subject or class inquiries (e.g. 'Do you have Chemistry?', 'Is there a Physics class?', 'Do you teach Python?', 'Chemistry classes'):
  Call `search_public_classes` with the specific query.

CRITICAL RULE FOR COURSE AVAILABILITY (VERY IMPORTANT):
- If `search_public_classes` returns `found: true`:
  State clearly that the class/course is available and present the real information from the result (subject, grade, medium, teacher, day/time, hall, fee).
- If `search_public_classes` returns `found: false`:
  You MUST state clearly and naturally that no class or course for that subject is currently listed or available at this institute.
  Example: "We don't currently have a [Subject] class listed at this institute. I can show you the classes that are currently available."
  DO NOT provide generic educational advice or academic definitions (e.g. NEVER say "Chemistry is the study of matter...").
  DO NOT recommend or invent a fictional class.
  DO NOT say "We probably offer..." or speculate.
  Never use general Gemini knowledge to invent classes, courses, schedules, teachers, or fees.

ERROR VS NOT-FOUND (MANDATORY DISTINCTION):
- If a tool returns an error or failure (e.g., `ok: false` or an error message):
  Reply: "I couldn't check that information right now. Please try again shortly."
  Do NOT claim there is no class merely because the system or tool failed.
- You must only tell the visitor that a class is not listed/available when the search returns successfully with `found: false`.
CLASSES_ENABLED;
        } else {
            $classSection = "- Class and course details lookup is disabled by the institute administrator. If asked about classes or courses, state that class details are currently not available.";
        }

        // 3. New / Upcoming Courses
        $newCoursesSection = '';
        if ($newCoursesEnabled) {
            $newCoursesSection = <<<NEW_ENABLED
NEW & UPCOMING COURSES:
- When a visitor asks about new courses, upcoming classes, or classes starting soon (e.g. 'What new courses do you have?', 'Any new classes?', 'What classes are starting soon?'):
  Call `get_new_public_classes`.
- If `found: true`: List the upcoming classes and their start dates.
- If `found: false` or empty: Say clearly: "There are no new courses currently listed."
  Never invent upcoming courses.
NEW_ENABLED;
        } else {
            $newCoursesSection = "- New courses lookup is disabled by the administrator. If asked about new courses, state that this information is currently not available.";
        }

        // 4. Teacher Details
        $teacherSection = $teacherEnabled
            ? "- When asked about teachers or lecturers (e.g. 'Who are your teachers?', 'Who teaches Mathematics?'): Call `get_public_teacher_details`."
            : "- Teacher details lookup is disabled by the administrator. If asked about teachers, state that teacher details are currently not available.";

        // 5. Attendance Privacy
        $attendanceSection = '';
        if (!$attendanceEnabled) {
            $attendanceSection = <<<ATTN_DISABLED
ATTENDANCE ACCESS: DISABLED
- Attendance lookup has been turned OFF by the administrator for this institute.
- If anyone asks for attendance (e.g. 'Show my attendance', 'Check attendance', 'Was I present'):
  You MUST reply:
  "Attendance lookup is not available through this assistant."
- Do NOT ask for student full name or parent name. Do not attempt verification.
ATTN_DISABLED;
        } else {
            $attendanceSection = <<<ATTN_ENABLED
ATTENDANCE PRIVACY & VERIFICATION FLOW:
- Attendance is STRICTLY PRIVATE.
- When a visitor asks to check attendance (e.g. 'Show my attendance', 'Check attendance', 'Was I present'):
  DO NOT call any tool yet.
  Ask the visitor:
  "To check attendance, please provide the student's full name and parent/guardian's full name."
- When the visitor provides the student name and parent/guardian name:
  Call `verify_student_for_attendance` with `student_name` and `parent_name`.
- If `verify_student_for_attendance` returns `{"verified": false}`:
  Reply:
  "I couldn't verify those details. Please check the student and parent/guardian names and try again."
  CRITICAL: Do NOT disclose whether the student exists, whether the parent name is wrong, or which field failed.
- If `verify_student_for_attendance` returns `{"verified": true}`:
  Confirm verification:
  "Thank you. Your details were verified. Would you like your recent attendance or attendance for a specific date?"
  Then call `get_verified_student_attendance` (with optional `date` if the user requested a specific date).
- If `get_verified_student_attendance` returns no records for a specific date:
  Say: "I don't have an attendance record for that date." (Do NOT automatically say the student was absent).
- ATTENDANCE DOES NOT UNLOCK OTHER DATA:
  Successful verification unlocks ATTENDANCE ONLY. Never expose student profile, contact numbers, NIC, addresses, fees, payments, or exam results.
ATTN_ENABLED;
        }

        return <<<PROMPT
You are {$assistantName}, the AI assistant for {$instituteName}.

ADMIN DESCRIPTION:
{$description}

ADMIN PURPOSE:
{$purpose}

==================================================
CONVERSATIONAL BEHAVIOR & ROLE
==================================================
- You should sound natural, friendly, helpful, warm, professional, and conversational.
- Do NOT behave like a rigid database search bot.
- Maintain the natural context and flow of the conversation across messages.
- You may engage in light normal conversation without calling tools.

GREETINGS & CASUAL CHAT:
- Examples: "Hi", "Hello", "How are you?", "Good morning", "Thank you", "I'm good", etc.
- Respond naturally, warmly, and briefly without calling database tools.
  Examples:
  - User: "Hi" -> "Hello! How can I help you today? 😊"
  - User: "How are you?" -> "I'm doing well, thanks for asking! How can I help you today?"
  - User: "I'm good." -> "Glad to hear that! What would you like to know about {$instituteName}?"

WHEN ASKED ABOUT YOURSELF / YOUR ROLE:
- If the visitor asks: "Who are you?", "Tell me about yourself", "What do you do?", "What can you help me with?", "I want to know about you":
  Explain your role naturally:
  "I'm {$assistantName}, the AI assistant for {$instituteName}. I'm here to help you learn about our classes, courses, teachers, schedules, new programs, institute information, and attendance where available. If you have a question, just ask me."
- Never mention internal technical terms like "Gemini", "system prompts", "APIs", "database", "PHP", "tools", or internal architecture.

==================================================
OUT-OF-SCOPE QUESTIONS & SMART REDIRECTION
==================================================
- Your main purpose is to help visitors with {$instituteName}.
- If the visitor asks about an unrelated topic, do NOT respond harshly or rigidly with "I cannot answer that" or "I am unable to...".
- Instead follow these steps:
  1. Recognize what they are asking about.
  2. If appropriate, provide only a very brief high-level acknowledgement (1 sentence).
  3. Explain that your main role is helping with {$instituteName}.
  4. Redirect naturally to relevant institute information.
  5. Do NOT become a full general-purpose chatbot. Do not provide long tutorials, recipes, or detailed unrelated answers.

EXAMPLE (Unrelated / AI query):
- User: "I want to know about ChatGPT."
- Assistant: "It sounds like you're asking about ChatGPT, an AI assistant. My main role here is to help with {$instituteName}, so I can help you with our classes, courses, teachers, schedules, new programs, and institute information.

If you'd like, you can ask me something like:
• What classes do you offer?
• Do you have Chemistry classes?
• Who are your teachers?
• What new courses are available?"

SMART REDIRECTION (Educational / Subject / Skill inquiries):
- When an unrelated or educational question could actually relate to a course {$instituteName} may offer (e.g., "How can I learn Python?", "I want to learn Chemistry", "Tell me about coding", "Do you teach web development?"):
  Redirect it intelligently:
  1. Give a brief 1-sentence high-level acknowledgement (e.g. "Python is a programming language commonly used for software development, automation, and AI. My main role here is helping with {$instituteName}, though.").
  2. State that you can check whether {$instituteName} currently offers a course for it.
  3. Call `search_public_classes` with the specific query (e.g. `query: "Python"` or `query: "coding"`).
  4. If found: Share the actual matching class/course information.
  5. If not found: State clearly: "We don't currently have a [Subject] class listed at {$instituteName}. If you'd like, I can show you the courses that are currently available."
  CRITICAL: Never claim that {$instituteName} offers a course unless an approved backend tool confirms it!

==================================================
TONE & STYLE GUIDELINES
==================================================
- Use a tone that is friendly, warm, professional, natural, helpful, and concise.
- Do not sound overly formal or robotic.
- Avoid repetitive robotic phrases such as:
  "I'm here to assist you with..."
  "I'm unable to..."
  "According to the database..."
  "According to my tools..."
- Instead of "I am unable to provide information regarding that subject.", say:
  "I don't have that information here, but I can help you with {$instituteName}'s classes and courses."
- Small friendly emojis (such as 😊, 👋) may be used occasionally when appropriate. Do not use emojis in every message, and do not make professional course/class information look childish.

==================================================
CRITICAL DATA RULE
==================================================
- Friendly conversation may come from you.
- Institute facts must NOT.
- Anything concerning available courses, classes, teachers, schedules, fees, new courses, institute details, and attendance MUST come strictly from approved institute configuration or approved backend tools.
- Never invent institute information or courses just to keep the conversation flowing.

{$instSection}

{$classSection}

{$newCoursesSection}

{$teacherSection}

{$attendanceSection}

SECURITY & PRIVACY RULES:
- Never reveal internal database IDs, student IDs, class IDs, enrollment IDs, or internal table schemas.
- Never output SQL, raw API parameters, or code.
- Never reveal staff passwords, NICs, bank details, or private financial details.
- Never access or reveal data from other institutes.
- Rely strictly on retrieved tool results for all factual claims.
PROMPT;
    }

    private function buildTools(array $permissions): array
    {
        $declarations = [];

        // 1. Institute Details Tool
        if (!empty($permissions['institute_details']['enabled'])) {
            $declarations[] = [
                'name' => 'get_public_institute_details',
                'description' => 'Return approved public information about this institute including about, public address, phone, email, website, opening hours, registration info, and facilities.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new stdClass(),
                ],
            ];
        }

        // 2. All Public Classes Tool
        if (!empty($permissions['class_details']['enabled'])) {
            $declarations[] = [
                'name' => 'get_public_classes',
                'description' => 'Return all approved public classes and courses available at this institute.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new stdClass(),
                ],
            ];

            // 3. Search Public Classes Tool
            $declarations[] = [
                'name' => 'search_public_classes',
                'description' => 'Search for specific classes, subjects, grades, mediums, or courses offered by this institute. Returns actual matching records or found=false if not offered.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => [
                            'type' => 'STRING',
                            'description' => 'The subject, class name, grade, or course name to search for (e.g. "Chemistry", "Physics", "Combined Mathematics", "Python").',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ];
        }

        // 4. New / Upcoming Courses Tool
        if (!empty($permissions['new_courses']['enabled'])) {
            $declarations[] = [
                'name' => 'get_new_public_classes',
                'description' => 'Return new or upcoming classes and courses that are starting soon or currently open for enrollment at this institute.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new stdClass(),
                ],
            ];
        }

        // 5. Public Teachers Tool
        if (!empty($permissions['teacher_details']['enabled'])) {
            $declarations[] = [
                'name' => 'get_public_teacher_details',
                'description' => 'Return approved public teacher names and their assigned classes for the current institute.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'teacher_name' => [
                            'type' => 'STRING',
                            'description' => 'Optional teacher name to search for.',
                        ],
                    ],
                ],
            ];
        }

        // 6 & 7. Attendance Verification & Lookup Tools
        if (!empty($permissions['attendance_details']['enabled'])) {
            $declarations[] = [
                'name' => 'verify_student_for_attendance',
                'description' => 'Verify a student identity for private attendance access. Requires the student full name and the parent or guardian full name. Both must match the same student.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'student_name' => [
                            'type' => 'STRING',
                            'description' => 'Full name of the student.',
                        ],
                        'parent_name' => [
                            'type' => 'STRING',
                            'description' => 'Full name of the parent or guardian.',
                        ],
                    ],
                    'required' => ['student_name', 'parent_name'],
                ],
            ];

            $declarations[] = [
                'name' => 'get_verified_student_attendance',
                'description' => 'Retrieve attendance records for a student who was previously verified during this session. Do not call this if the user has not been verified yet.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'date' => [
                            'type' => 'STRING',
                            'description' => 'Optional exact date in YYYY-MM-DD format. Omit if general or recent attendance is requested.',
                        ],
                    ],
                ],
            ];
        }

        if ($declarations === []) {
            return [];
        }

        return [
            [
                'function_declarations' => $declarations,
            ],
        ];
    }

    private function executeTool(array $call, array $permissions, int $instituteId): array
    {
        $name = (string) ($call['name'] ?? '');
        $arguments = is_array($call['arguments'] ?? null) ? $call['arguments'] : [];

        return match ($name) {
            'get_public_institute_details' => $this->requireAndRun(
                $permissions,
                'institute_details',
                function () use ($instituteId) {
                    $instRepo = new InstituteRepository(Database::connection());
                    return $instRepo->getPublicDetails($instituteId);
                }
            ),

            'get_public_classes' => $this->requireAndRun(
                $permissions,
                'class_details',
                fn() => $this->connector->publicClasses($instituteId)
            ),

            'search_public_classes' => $this->requireAndRun(
                $permissions,
                'class_details',
                function () use ($arguments, $instituteId) {
                    $query = isset($arguments['query']) ? (string) $arguments['query'] : '';
                    return $this->connector->searchClasses($instituteId, $query);
                }
            ),

            'get_new_public_classes' => $this->requireAndRun(
                $permissions,
                'new_courses',
                fn() => $this->connector->newClasses($instituteId)
            ),

            'get_public_teacher_details' => $this->requireAndRun(
                $permissions,
                'teacher_details',
                fn() => $this->connector->publicTeachers(
                    $instituteId,
                    isset($arguments['teacher_name']) && is_string($arguments['teacher_name'])
                        ? trim($arguments['teacher_name'])
                        : null
                )
            ),

            'verify_student_for_attendance' => $this->requireAndRun(
                $permissions,
                'attendance_details',
                function () use ($arguments, $instituteId) {
                    $studentName = isset($arguments['student_name']) ? (string) $arguments['student_name'] : '';
                    $parentName = isset($arguments['parent_name']) ? (string) $arguments['parent_name'] : '';

                    return AttendanceVerification::verify($studentName, $parentName, $instituteId);
                }
            ),

            'get_verified_student_attendance' => $this->requireAndRun(
                $permissions,
                'attendance_details',
                function () use ($arguments, $instituteId) {
                    if (!AttendanceVerification::isSessionVerified($instituteId)) {
                        return [
                            'ok' => false,
                            'error' => 'Please provide the student\'s full name and parent/guardian\'s full name to check attendance.',
                        ];
                    }

                    $studentId = AttendanceVerification::getVerifiedStudentId($instituteId);
                    if ($studentId === null) {
                        return [
                            'ok' => false,
                            'error' => 'Attendance verification expired. Please verify details again.',
                        ];
                    }

                    $date = isset($arguments['date']) && is_string($arguments['date']) && trim($arguments['date']) !== ''
                        ? trim($arguments['date'])
                        : null;

                    return $this->connector->verifiedStudentAttendance($studentId, $instituteId, $date);
                }
            ),

            default => [
                'ok' => false,
                'error' => 'Unknown or unavailable tool.',
            ],
        };
    }

    private function requireAndRun(
        array $permissions,
        string $permissionKey,
        callable $callback
    ): array {
        if (empty($permissions[$permissionKey]['enabled'])) {
            return [
                'ok' => false,
                'error' => 'This information category is disabled by the administrator.',
            ];
        }

        try {
            return [
                'ok' => true,
                'data' => $callback(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => Env::bool('APP_DEBUG', false)
                    ? $e->getMessage()
                    : 'I couldn\'t retrieve that information right now. Please try again shortly.',
            ];
        }
    }

    private function extractFunctionCalls(array $modelContent): array
    {
        $calls = [];

        foreach (($modelContent['parts'] ?? []) as $part) {
            if (!is_array($part) || !isset($part['functionCall'])) {
                continue;
            }

            $fc = $part['functionCall'];
            if (!is_array($fc)) {
                continue;
            }

            $name = (string) ($fc['name'] ?? '');
            $callId = (string) ($fc['id'] ?? '');
            $arguments = $fc['args'] ?? [];

            if ($name === '') {
                continue;
            }

            $calls[] = [
                'name' => $name,
                'call_id' => $callId,
                'arguments' => is_array($arguments) ? $arguments : [],
            ];
        }

        return $calls;
    }

    private function extractText(array $modelContent): string
    {
        $text = '';

        foreach (($modelContent['parts'] ?? []) as $part) {
            if (!is_array($part)) {
                continue;
            }

            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }

    private function buildContents(array $history, string $message): array
    {
        $contents = [];

        $history = array_slice($history, -10);

        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = (string) ($item['role'] ?? '');
            $text = trim((string) ($item['content'] ?? ''));

            if ($text === '') {
                continue;
            }

            $text = mb_substr($text, 0, 2000);

            if ($role === 'user') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $text],
                    ],
                ];
            } elseif ($role === 'assistant') {
                $contents[] = [
                    'role' => 'model',
                    'parts' => [
                        ['text' => $text],
                    ],
                ];
            }
        }

        $messageText = mb_substr(trim($message), 0, 2000);
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $messageText],
            ],
        ];

        return $contents;
    }
}
