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

        $classEnabled = !empty($permissions['class_details']['enabled']);
        $teacherEnabled = !empty($permissions['teacher_details']['enabled']);
        $attendanceEnabled = !empty($permissions['attendance_details']['enabled']);

        $classSection = $classEnabled
            ? "- Class schedules, subjects, grades, mediums, class times, days, halls, and teacher names are public. When asked about classes or schedules: call `get_public_classes`."
            : "- Class details lookup is disabled by the institute administrator. If asked about classes, state that class details are currently not available.";

        $teacherSection = $teacherEnabled
            ? "- When asked about teachers: call `get_public_teacher_details`."
            : "- Teacher details lookup is disabled by the institute administrator. If asked about teachers, state that teacher details are currently not available.";

        $attendanceSection = '';
        if (!$attendanceEnabled) {
            $attendanceSection = <<<ATTN_DISABLED
ATTENDANCE ACCESS: DISABLED
- Attendance lookup has been turned OFF by the administrator for this institute.
- If anyone asks for attendance (e.g. "Show my attendance", "Check attendance", "Was I present"):
  You MUST reply:
  "Attendance lookup is not available through this assistant."
- Do NOT ask for student full name or parent name. Do not attempt verification.
ATTN_DISABLED;
        } else {
            $attendanceSection = <<<ATTN_ENABLED
ATTENDANCE PRIVACY & VERIFICATION FLOW:
- Attendance is STRICTLY PRIVATE.
- When a visitor asks to check attendance (e.g. "Show my attendance", "Check attendance", "Was I present"):
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
You are {$assistantName}, the official public AI Assistant for {$instituteName}.

ADMIN DESCRIPTION:
{$description}

ADMIN PURPOSE:
{$purpose}

SCOPE & BEHAVIOR:
- You help public visitors, prospective students, students, and parents with information regarding {$instituteName}.
- You must speak clearly, politely, naturally, and warmly.
- Keep responses concise, helpful, and non-technical.

GREETINGS:
- For simple greetings like "hi", "hello", "good morning", respond naturally and warmly:
  "Hello! How can I help you with {$instituteName} today?"
- Do NOT call database tools for a simple greeting.

PUBLIC INFORMATION:
{$classSection}
{$teacherSection}

{$attendanceSection}

OUT-OF-SCOPE QUESTIONS:
- You are dedicated solely to {$instituteName}.
- If someone asks something unrelated (e.g., "How do I learn Python?", programming questions, recipes, trivia, weather):
  Politely and briefly reply:
  "I'm the AI assistant for {$instituteName}. I can help with classes, schedules, teachers, and attendance information."
  Do not act as a general-purpose chatbot.

SECURITY RULES:
- Never reveal internal database IDs, student IDs, class IDs, enrollment IDs, or internal table structure.
- Never output SQL, API details, Gemini terminology, tool names, or code snippets.
- Never reveal staff passwords, NICs, bank details, or private financial details.
- Never access or reveal data from other institutes.
- Do not invent database records. Rely strictly on retrieved tool results.
PROMPT;
    }

    private function buildTools(array $permissions): array
    {
        $declarations = [];

        if (!empty($permissions['class_details']['enabled'])) {
            $declarations[] = [
                'name' => 'get_public_classes',
                'description' => 'Return approved public class details, schedules, subjects, grades, mediums, days, times, halls, and teacher names for the current institute.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new stdClass(),
                ],
            ];
        }

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
            'get_public_classes' => $this->requireAndRun(
                $permissions,
                'class_details',
                fn() => $this->connector->publicClasses($instituteId)
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
