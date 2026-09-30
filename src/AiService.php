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

    public static function detectLatinLanguage(string $text): string
    {
        $lower = mb_strtolower(trim($text));

        // High-confidence Singlish phrases
        $singlishPhrases = [
            '/\b(class|classes|course|courses|batch|sir|miss|teacher|timetable|schedule|fee|fees|subject|attendance|admission|registration|register|results|result|notes|paper|revision|theory|hall|institute)\s+(eka|eke|ekak|ekata|ekada|monawada|kiyada|thiyenawada|thiyenne|nadda|kawda|denna|karanna)\b/i',
            '/\b(check|register|join|enroll|apply)\s+(karanna|karanne|wenna|wenne|karannada|karamuda|puluwanda|ona|one)\b/i',
            '/\b(balanna|karanna|denna|ganna|enna|yanna|join wenna|register wenna)\s+puluwanda\b/i',
            '/\b(thiyenawa|thiyanawa|thiyenne|thiyena)\s+(nedda|nadda|da)\b/i',
            '/\b(classes|panthi)\s+(monawada|thiyenawada|thiyenne)\b/i',
            '/\b(teacher|sir|miss)\s+kawda\b/i',
            '/\bfee\s+(eka\s+)?kiyada\b/i',
            '/\b(ada|heta|iye)\s+(classes|class|panthi|thiyenawa|thiyenawada|thiyenne)\b/i',
            '/\b(sir|miss)\s+ge\b/i',
            '/\b(wenna|karanna|balanna|denna)\s+(ona|one|puluwanda)\b/i',
        ];

        $singlishScore = 0;
        foreach ($singlishPhrases as $pattern) {
            if (preg_match($pattern, $lower)) {
                $singlishScore += 3;
            }
        }

        // Distinctive Singlish words
        $singlishWords = [
            'monawada', 'monada', 'mokakda', 'mokadda', 'mokak', 'mokada', 'moko',
            'kohomada', 'kohomadha', 'koheda', 'kohedha', 'kawda', 'kauda', 'kiyada', 'kiyadha',
            'kiyatada', 'kiyathada', 'kiyathadha', 'kawadda', 'kawadha', 'kavadada', 'aei', 'aeyi',
            'mata', 'mage', 'magen', 'mama', 'oya', 'oyage', 'oyaage', 'oyata', 'oyala', 'oyalage', 'oyalata',
            'ape', 'apata', 'apita', 'eya', 'eyage', 'eyata', 'eyala', 'eyalage',
            'meka', 'meke', 'mekata', 'mewa', 'mewaye', 'mehe', 'methana', 'methanata',
            'araka', 'arake', 'ethan', 'ethana', 'othan', 'othana', 'ohe',
            'thiyenawa', 'thiyenawada', 'thiyanawa', 'thiyanawada', 'thiyenne', 'thiyenna',
            'thiyeda', 'thiyenam', 'thiyenawanam', 'thiyena', 'thiyana', 'thibba', 'thibeda', 'thibbada',
            'nadda', 'nedda', 'naeda', 'naedda', 'naha', 'naa', 'nehe', 'nee',
            'puluwanda', 'puluwan', 'barida', 'beri', 'baa', 'baha', 'epa',
            'oneda', 'onada', 'ona', 'oona', 'ooneda',
            'balanna', 'balamuda', 'balamu', 'denna', 'denawada', 'dennako', 'denne',
            'ganna', 'gannawada', 'gannako', 'ganne', 'kiyanna', 'kiyanne', 'kiyanawada', 'kiyannako',
            'ahanna', 'ahanne', 'ahanawada', 'ewanna', 'ewannako', 'evanna',
            'danna', 'dannawada', 'danaganna', 'yanna', 'enna', 'liyanna', 'hoyanna', 'hoyaganna',
            'karanna', 'karanne', 'karalada', 'karamuda', 'wenna', 'wenne', 'wela', 'unada', 'wunada',
            'eka', 'eke', 'ekata', 'ekak', 'ekaka', 'ekada', 'ekakda',
            'gana', 'gena', 'visthara', 'wistara', 'wisthara', 'poddak', 'godak', 'tikak',
            'thamai', 'thamayi', 'thama', 'neda', 'needa', 'thawa', 'thavath', 'tawath', 'wage', 'vage',
            'ada', 'heta', 'hete', 'iye', 'udenma', 'hawasa', 'hawasta', 'dawalta', 'dawasa',
            'dawasata', 'sathiyata', 'sathiya', 'maaseta', 'maseta', 'maase', 'aurudda', 'awurudda',
            'panthi', 'panthiya', 'panthiye', 'padam', 'padama', 'aluth', 'parana', 'lamai', 'sedisi', 'welawa', 'velawa'
        ];

        $tokens = preg_split('/[\s,.;:!?()[\]{}"\'\\\\\/<>+=_-]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $token) {
            if (in_array($token, $singlishWords, true)) {
                $singlishScore += 2;
            } elseif (preg_match('/(wada|nawada|nadda|puluwanda|kiyada|karanna|balanna|denna|wenne|karanne|thiyenne)$/i', $token)) {
                $singlishScore += 1;
            }
        }

        // Distinctive English grammatical phrases
        $englishPhrases = [
            '/\b(what|where|when|who|why|how|which)\s+(is|are|was|were|do|does|did|can|could|will|would|should|have|has)\b/i',
            '/\b(can|could|will|would|do|does|did|is|are)\s+you\b/i',
            '/\b(is|are)\s+there\b/i',
            '/\b(i\s+want|i\s+would\s+like|i\s+need|please\s+(tell|give|show|check|send|help))\b/i',
            '/\bhow\s+can\s+i\b/i',
        ];

        $englishScore = 0;
        foreach ($englishPhrases as $pattern) {
            if (preg_match($pattern, $lower)) {
                $englishScore += 3;
            }
        }

        $englishStopwords = [
            'what', 'where', 'when', 'who', 'why', 'how', 'which',
            'is', 'are', 'am', 'was', 'were', 'do', 'does', 'did',
            'can', 'could', 'will', 'would', 'should', 'shall', 'may', 'might', 'must',
            'have', 'has', 'had', 'please', 'tell', 'show', 'check', 'find', 'list',
            'about', 'for', 'the', 'this', 'that', 'these', 'those', 'there', 'their',
            'our', 'your', 'my', 'any', 'some', 'all', 'want', 'need', 'know',
            'available', 'today', 'tomorrow', 'yesterday', 'institute', 'classes',
            'class', 'course', 'courses', 'student', 'lecturer', 'teacher', 'attendance',
            'information', 'details', 'contact', 'fee', 'fees', 'timetable', 'schedule',
            'registration', 'register', 'address', 'location', 'timing', 'timings', 'give',
            'provide', 'help', 'with', 'from', 'much', 'many', 'cost'
        ];

        foreach ($tokens as $token) {
            if (in_array($token, $englishStopwords, true)) {
                $englishScore += 1;
            }
        }

        if ($singlishScore >= 2 && $singlishScore >= $englishScore) {
            return 'si';
        }

        if ($englishScore >= 2 && $singlishScore === 0) {
            return 'en';
        }

        if ($englishScore > $singlishScore && $singlishScore === 0) {
            return 'en';
        }

        if ($singlishScore >= 2) {
            return 'si';
        }

        return 'auto';
    }

    public static function detectLanguage(string $text): ?string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        // Pure numbers, phone numbers, or symbols
        if (preg_match('/^[\d\s+\-().,\/#]+$/u', $trimmed)) {
            return null;
        }

        $normalized = mb_strtolower(trim(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $trimmed)));

        // Common short student IDs like STU001, STU-2026-001
        if (preg_match('/^[a-z]{1,5}[-_]?\d+[-_]?\d*$/i', $normalized)) {
            return null;
        }

        // Must contain at least one letter
        if (!preg_match('/[\x{0D80}-\x{0DFF}\x{0B80}-\x{0BFF}a-zA-Z]/u', $trimmed)) {
            return null;
        }

        // Common short ambiguous words
        $ambiguousWords = [
            'hi', 'hello', 'hey', 'ok', 'okay', 'yes', 'no',
            'thanks', 'thank you', 'thx', 'bye', 'goodbye', 'k'
        ];
        if (in_array($normalized, $ambiguousWords, true)) {
            return null;
        }

        $sinhalaChars = preg_match_all('/[\x{0D80}-\x{0DFF}]/u', $trimmed);
        $tamilChars = preg_match_all('/[\x{0B80}-\x{0BFF}]/u', $trimmed);
        $latinChars = preg_match_all('/[a-zA-Z]/u', $trimmed);

        // Sinhala Unicode dominant / mixed
        if ($sinhalaChars > 0 && $tamilChars === 0) {
            if ($latinChars === 0) {
                return 'si';
            }
            $tokens = preg_split('/[\s,.;:!?()[\]{}"\'\\\\\/<>+=_-]+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
            $sinhalaWords = 0;
            $englishWords = 0;
            foreach ($tokens as $token) {
                if (preg_match('/[\x{0D80}-\x{0DFF}]/u', $token)) {
                    $sinhalaWords++;
                } elseif (preg_match('/^[a-zA-Z]+$/u', $token)) {
                    $englishWords++;
                }
            }
            return ($sinhalaWords >= $englishWords || $sinhalaChars >= $latinChars) ? 'si' : 'en';
        }

        // Tamil Unicode dominant / mixed
        if ($tamilChars > 0 && $sinhalaChars === 0) {
            if ($latinChars === 0) {
                return 'ta';
            }
            $tokens = preg_split('/[\s,.;:!?()[\]{}"\'\\\\\/<>+=_-]+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
            $tamilWords = 0;
            $englishWords = 0;
            foreach ($tokens as $token) {
                if (preg_match('/[\x{0B80}-\x{0BFF}]/u', $token)) {
                    $tamilWords++;
                } elseif (preg_match('/^[a-zA-Z]+$/u', $token)) {
                    $englishWords++;
                }
            }
            return ($tamilWords >= $englishWords || $tamilChars >= $latinChars) ? 'ta' : 'en';
        }

        if ($sinhalaChars > 0 && $tamilChars > 0) {
            return ($sinhalaChars >= $tamilChars) ? 'si' : 'ta';
        }

        // Latin only (English, Singlish, or ambiguous)
        if ($latinChars > 0) {
            return self::detectLatinLanguage($trimmed);
        }

        return null;
    }

    public static function resolveLanguage(
        string $message,
        ?string $clientResponse = null,
        ?string $clientDetected = null,
        string $preferredLanguage = 'en'
    ): string {
        $allowed = ['en', 'si', 'ta', 'auto'];
        $preferred = in_array(strtolower(trim($preferredLanguage)), ['en', 'si', 'ta'], true)
            ? strtolower(trim($preferredLanguage))
            : 'en';

        // 1. Clearly detectable language of the CURRENT user message
        $detected = self::detectLanguage($message);
        if ($detected !== null && in_array($detected, $allowed, true)) {
            return $detected;
        }

        if ($clientDetected !== null && in_array($clientDetected, $allowed, true)) {
            return $clientDetected;
        }

        // 2. Client response language (which tracks current conversation language)
        if ($clientResponse !== null && in_array($clientResponse, ['en', 'si', 'ta'], true)) {
            return $clientResponse;
        }

        // 3. Manually selected preferred language
        if (in_array($preferred, ['en', 'si', 'ta'], true)) {
            return $preferred;
        }

        // 4. English fallback
        return 'en';
    }

    /** @var string[] */
    private array $executedTools = [];

    public function getExecutedTools(): array
    {
        return $this->executedTools;
    }

    public function reply(
        string $message,
        array $history = [],
        string $widgetKey = '',
        int $assistantId = 1,
        string $language = 'en',
        string $preferredLanguage = 'en'
    ): string {
        $this->executedTools = [];
        $language = self::resolveLanguage($message, $language, null, $preferredLanguage);

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
                    ['text' => $this->buildInstructions($assistant, $permissions, $language)],
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
                $toolName = (string) ($call['name'] ?? '');
                if ($toolName !== '') {
                    $this->executedTools[] = $toolName;
                }
                $result = $this->executeTool($call, $permissions, $instituteId);

                $toolResponse = (isset($result['verified']))
                    ? $result
                    : ['result' => $result];

                $respPart = [
                    'functionResponse' => [
                        'name' => $call['name'],
                        'response' => $toolResponse,
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

    private function buildInstructions(array $assistant, array $permissions, string $language = 'en'): string
    {
        $assistantName = (string) ($assistant['name'] ?? 'GETMORE AI');
        $instituteName = (string) ($assistant['institute_name'] ?? 'our institute');
        $description = (string) ($assistant['description'] ?? '');
        $purpose = (string) ($assistant['purpose'] ?? '');

        $langMap = [
            'en' => 'English',
            'si' => 'Sinhala',
            'ta' => 'Tamil',
            'auto' => 'Automatic Detection (English or Romanized Sinhala)',
        ];
        $selectedLangName = $langMap[$language] ?? 'English';

        $langSpecificGuide = match ($language) {
            'si' => <<<LANG_SI
- SELECTED LANGUAGE: Sinhala
- Reply primarily and fluently in natural, polite Sinhala (සිංහල).
- CRITICAL FOR ROMANIZED SINHALA (SINGLISH):
  The user may write in Sinhala using English/Latin alphabet (Romanized Sinhala / Singlish, such as "mata ada thiyena classes monawada?", "mage attendance eka balanna", "heta classes thiyenawada?", "online join wenna puluwanda?").
  You must understand Romanized Sinhala and ALWAYS reply using proper SINHALA UNICODE SCRIPT (සිංහල අකුරු).
  NEVER respond in English to Romanized Sinhala / Singlish messages.
  NEVER respond in Romanized Sinhala (Latin letters). Always use proper Sinhala script.
- For attendance verification prompt, provide natural Sinhala while retaining the exact public identifier labels:
  "ඔබගේ පැමිණීමේ තොරතුරු පරීක්ෂා කිරීමට කරුණාකර පහත තොරතුරු ලබා දෙන්න:

• Student ID / Index Number
• Parent Mobile Number"
- For attendance verification failure, provide only:
  "මට එම තොරතුරු තහවුරු කර ගැනීමට නොහැකි විය. කරුණාකර ඔබගේ Student ID / Index Number සහ Parent Mobile Number පරීක්ෂා කර නැවත උත්සාහ කරන්න."
- For verified confirmation:
  "ස්තූතියි, ඔබගේ තොරතුරු තහවුරු විය. ඔබට ඔබගේ මෑතකාලීන පැමිණීමේ වාර්තා හෝ නිශ්චිත දිනයක පැමිණීම බැලීමට අවශ්‍යද?"
- Out-of-scope redirection in Sinhala:
  "මට උපකාර කළ හැක්කේ මෙම ආයතනයට අදාළ පන්ති, දේශකවරුන්, කාලසටහන්, පැමිණීම සහ ආයතනික තොරතුරු සඳහා පමණි."
- Do NOT translate exact official course or class names (e.g. keep "Combined Mathematics 2026 Theory" as returned by tools). Explain around them in Sinhala.
LANG_SI,
            'ta' => <<<LANG_TA
- SELECTED LANGUAGE: Tamil
- Reply primarily and fluently in natural, polite Tamil (தமிழ்).
- For attendance verification prompt, provide natural Tamil while retaining the exact public identifier labels:
  "உங்கள் வருகை விவரங்களைச் சரிபார்க்க, தயவுசெய்து பின்வரும் விவரங்களை வழங்கவும்:

• Student ID / Index Number
• Parent Mobile Number"
- For attendance verification failure, provide only:
  "அந்த விவரங்களை என்னால் சரிபார்க்க முடியவில்லை. தயவுசெய்து உங்கள் Student ID / Index Number மற்றும் Parent Mobile Number ஆகியவற்றைச் சரிபார்த்து மீண்டும் முயற்சிக்கவும்."
- For verified confirmation:
  "நன்றி, உங்கள் விவரங்கள் சரிபார்க்கப்பட்டன. உங்கள் சமீபத்திய வருகைப் பதிவையோ அல்லது குறிப்பிட்ட தேதிக்கான வருகைப் பதிவையோ பார்க்க விரும்புகிறீர்களா?"
- Out-of-scope redirection in Tamil:
  "என்னால் இந்த நிறுவனம் தொடர்பான வகுப்புகள், விரிவுரையாளர்கள், கால அட்டவணைகள், வருகை மற்றும் நிறுவன விவரங்களுக்கு மட்டுமே உதவ முடியும்."
- Do NOT translate exact official course or class names (e.g. keep "Combined Mathematics 2026 Theory" as returned by tools). Explain around them in Tamil.
LANG_TA,
            'auto' => <<<LANG_AUTO
- SELECTED LANGUAGE: Automatic Detection (English or Romanized Sinhala)
- The user's input is written in Latin characters. You must determine whether the message is English or Romanized Sinhala (Singlish):
  * If the user wrote in Romanized Sinhala / Singlish (e.g. "mata ada thiyena classes monawada?", "mage attendance eka balanna", "heta classes thiyenawada?", "online join wenna puluwanda?"):
    You MUST respond in proper SINHALA UNICODE SCRIPT (සිංහල අකුරු). Do NOT respond in English or Romanized Sinhala.
  * If the user wrote in genuine English (e.g. "What classes are available today?", "Can you check my attendance?", "Where is the institute?"):
    Respond in natural, fluent English.
- If out-of-scope, provide the scope redirection in the respective language (Sinhala script if Singlish, English if English).
- Do NOT translate exact official course or class names or database codes.
LANG_AUTO,
            default => <<<LANG_EN
- SELECTED LANGUAGE: English
- Reply in fluent, natural English.
- For attendance verification prompt:
  "To check attendance, please provide:

• Student ID / Index Number
• Parent Mobile Number"
- For attendance verification failure:
  "I couldn't verify those details. Please check your Student ID / Index Number and Parent Mobile Number and try again."
- For verified confirmation:
  "Thanks, your details have been verified. Would you like to see your recent attendance or attendance for a specific date?"
- Out-of-scope redirection in English:
  "I can only help with information related to {$instituteName}, such as classes, lecturers, schedules, attendance and institute details."
- Course names, proper names, IDs, codes, and original data values should remain unchanged.
LANG_EN,
        };

        $languageSection = <<<LANG_SECTION
==================================================
RESPONSE LANGUAGE FOR THIS TURN: {$selectedLangName}
==================================================
- RESOLVED RESPONSE LANGUAGE FOR THIS TURN: {$selectedLangName}.
- DYNAMIC TURN-BASED LANGUAGE DIRECTIVE:
  Respond to this turn in the resolved response language ({$selectedLangName}).
  The user's language may change from one message to the next (e.g., from Sinhala to English, or from English to Sinhala or Tamil).
  Do NOT continue using the previous language if the current user message or turn is in another supported language.
  Always produce your entire response for this turn in {$selectedLangName}.
- STRICT SCOPE RESTRICTION APPLIES IN ALL LANGUAGES:
  If a user asks an out-of-scope question, respond with the short scope-redirection message in the resolved response language ({$selectedLangName}).
- DO NOT TRANSLATE DATABASE VALUES:
  Keep original values such as institute names, lecturer/teacher names, course names, class names (e.g. "Combined Mathematics 2026 Theory"), dates, numbers, codes, student IDs, phone numbers, and URLs unchanged unless translation data explicitly exists.
{$langSpecificGuide}
LANG_SECTION;

        $instDetailsEnabled = !empty($permissions['institute_details']['enabled']);
        $classEnabled = !empty($permissions['class_details']['enabled']);
        $teacherEnabled = !empty($permissions['teacher_details']['enabled']);
        $newCoursesEnabled = !empty($permissions['new_courses']['enabled']);
        $attendanceEnabled = !empty($permissions['attendance_details']['enabled']);

        // 1. Institute Info Instructions
        $instSection = $instDetailsEnabled
            ? <<<INST_ENABLED
INSTITUTE DETAILS & LOCATION:
- When a visitor asks about the institute (e.g., about, description, address, location, contact info, phone, email, website, opening hours, facilities, registration info):
  Call `get_public_institute_details`.
- IMPORTANT LOCATION EXCEPTION:
  If the question is specifically about the CURRENT institute location (e.g. 'Where is the institute?', 'What is your address?', 'How can I contact the institute?', 'Where are you located?'):
  Return ONLY the institute's configured location, address, and contact details from `get_public_institute_details`.
  Do NOT generate general travel routes, bus routes, train schedules, or directions.
INST_ENABLED
            : "- Institute details lookup is disabled by the administrator. If asked about the institute, location, address, or contact details, state that institute details are currently unavailable. Do not speculate or provide general travel information.";

        // 2. Class & Course Search Instructions
        $classSection = '';
        if ($classEnabled) {
            $classSection = <<<CLASSES_ENABLED
CLASS & COURSE INFORMATION:
- For general questions about what classes or courses are offered (e.g. 'What classes do you have?', 'What courses are available?', 'Show your classes'):
  Call `get_public_classes`.
- For specific subject or class inquiries (e.g. 'Do you have Chemistry?', 'Is there a Physics class?', 'Do you teach Python?', 'Chemistry classes', 'How can I learn Python?'):
  Call `search_public_classes` with the specific query (e.g. query: "Chemistry", query: "Python").

CRITICAL RULES FOR COURSE AVAILABILITY & SEARCH:
- If `search_public_classes` returns `found: true`:
  State clearly that the class/course is available and present the real information from the result (subject, grade, medium, teacher, day/time, hall, fee).
- If `search_public_classes` returns `found: false`:
  Say clearly: "We don't currently have a [Subject] class listed at this institute."
  (For example, if the user asks "How can I learn Python?", search for Python courses. If none exists, say that no Python course is currently listed at {$instituteName}. DO NOT give a general Python learning guide, tutorial, or coding advice).
- NEVER explain subjects, academic concepts, or programming languages generally (e.g. NEVER define what Chemistry is or how to learn Python).
- NEVER use general knowledge to invent classes, courses, schedules, teachers, or fees.
- If information is unavailable, say: "I don't have that information available for this institute."

ERROR VS NOT-FOUND:
- If a tool returns an error or failure (e.g., `ok: false`):
  Reply: "I couldn't check that information right now. Please try again shortly."
- You must only tell the visitor that a class is not listed when the search returns successfully with `found: false`.
CLASSES_ENABLED;
        } else {
            $classSection = "- Class and course details lookup is disabled by the institute administrator. If asked about classes or courses, state that class details are currently not available. Do not invent or list any classes.";
        }

        // 3. New / Upcoming Courses
        $newCoursesSection = '';
        if ($newCoursesEnabled) {
            $newCoursesSection = <<<NEW_ENABLED
NEW & UPCOMING COURSES:
- When a visitor asks about new courses, upcoming classes, or classes starting soon (e.g. 'What new courses do you have?', 'Any new classes?', 'What classes are starting soon?'):
  Call `get_new_public_classes`.
- If `found: true`: List the upcoming classes and their start dates cleanly and concisely.
- If `found: false` or empty: Say clearly: "There are no new courses currently listed."
  Never invent upcoming courses.
NEW_ENABLED;
        } else {
            $newCoursesSection = "- New courses lookup is disabled by the administrator. If asked about new courses, state that this information is currently not available.";
        }

        // 4. Teacher Details
        $teacherSection = $teacherEnabled
            ? <<<TEACHER_ENABLED
TEACHER & LECTURER DETAILS:
- When asked about teachers or lecturers (e.g. 'Who are your teachers?', 'Who teaches Mathematics?'):
  Call `get_public_teacher_details`.
- Return only the actual lecturer/teacher information returned by the tool.
TEACHER_ENABLED
            : "- Teacher details lookup is disabled by the administrator. The assistant must NOT provide teacher information even if known or inferred. State that teacher details are currently not available.";

        // 5. Attendance Privacy
        $attendanceSection = '';
        if (!$attendanceEnabled) {
            $attendanceSection = <<<ATTN_DISABLED
ATTENDANCE ACCESS: DISABLED
- Attendance lookup has been turned OFF by the administrator for this institute.
- The assistant must NOT start attendance verification.
- If anyone asks for attendance (e.g. 'Show my attendance', 'Check attendance', 'Was I present?', 'My attendance'):
  You MUST reply:
  "Attendance lookup is not available through this assistant."
- Do NOT ask for Student ID / Index Number or Parent Mobile Number. Do not attempt verification.
ATTN_DISABLED;
        } else {
            $attendanceSection = <<<ATTN_ENABLED
ATTENDANCE PRIVACY & VERIFICATION FLOW:
- Attendance information is private.
- When a visitor requests attendance and has not yet been verified (e.g. 'Check my attendance', 'Show my attendance', 'Was I present?', 'My attendance'):
  Do NOT call any tool yet.
  Ask for:
  1. Student ID / Index Number
  2. Parent Mobile Number

  Example prompt:
  "To check attendance, please provide:

• Student ID / Index Number
• Parent Mobile Number"

CRITICAL ATTENDANCE INSTRUCTIONS:
- Do NOT ask for student full name, parent name, or student database ID.
- Never request or expose the internal database student ID (students.id).
- Call verify_student_for_attendance only after both Student ID / Index Number and Parent Mobile Number have been provided.
- If verify_student_for_attendance returns {"verified": false}:
  Give only a generic verification failure message:
  "I couldn't verify those details. Please check your Student ID / Index Number and Parent Mobile Number and try again."
  CRITICAL: DO NOT say whether the student exists, whether the mobile is wrong, or which field failed. Do not disclose any clues.
- If verify_student_for_attendance returns {"verified": true}:
  Reply:
  "Thanks, your details have been verified. Would you like to see your recent attendance or attendance for a specific date?"
  Then call get_verified_student_attendance (with optional date if the user requested a specific date).
- If get_verified_student_attendance returns no records for a specific date:
  Say: "I don't have an attendance record for that date." (Do NOT automatically say the student was absent).
- ATTENDANCE ONLY: Successful verification unlocks ONLY attendance. Never expose student profile, student phone, parent phone, address, NIC, email, fees, payments, exam results, marks, documents, or other students' information.
ATTN_ENABLED;
        }

        return <<<PROMPT
You are {$assistantName}, the dedicated AI assistant for {$instituteName}.

ADMIN DESCRIPTION:
{$description}

ADMIN PURPOSE:
{$purpose}

{$languageSection}

==================================================
SCOPE RESTRICTION: STRICT INSTITUTE ASSISTANT ONLY
==================================================
You are a specialized institute assistant, not a general-purpose AI assistant.

The AI Assistant is NOT:
- a general chatbot
- a personal friend
- a travel assistant
- a news assistant
- a coding assistant
- a general knowledge assistant
- a medical assistant
- a financial assistant
- a personal advice assistant

It is ONLY an assistant for the CURRENT institute/organization: {$instituteName}.

Before answering every user message, determine whether the request is related to:
- the current institute ({$instituteName}),
- its classes,
- courses,
- lecturers,
- schedules,
- services,
- facilities,
- contact details,
- attendance,
- or another explicitly enabled institute feature.

If it is unrelated, do NOT answer the underlying question.
Give ONLY a short scope-redirection response:
"I can only help with information related to {$instituteName}, such as classes, lecturers, schedules, attendance and institute details."
Never provide general knowledge merely because you know the answer.

==================================================
ALLOWED TOPICS
==================================================
The assistant may answer ONLY information related to {$instituteName} and enabled permissions, such as:
- Institute details
- Institute description
- Institute address
- Contact information
- Opening hours
- Facilities
- Registration information
- Classes
- Courses
- Monthly classes
- New/upcoming classes
- Class schedules
- Timetables
- Lecturer/teacher information
- Course availability
- Institute services
- Attendance, only after required verification
- Other information explicitly configured for this institute

Only answer categories that are enabled for the current assistant.

==================================================
SMALL TALK
==================================================
Allow very small conversational messages such as:
- Hi
- Hello
- Good morning
- How are you?
- Who are you?
- Thank you
- Bye

Responses must be SHORT and redirect back to institute assistance.

Examples:
- User: "Hi"
  Assistant: "Hi! How can I help you with the institute today?"
- User: "How are you?"
  Assistant: "I'm doing well, thank you. How can I help you with classes or institute information?"
- User: "Who are you?"
  Assistant: "I'm the AI assistant for {$instituteName}. I can help with classes, lecturers, schedules and other available institute information."
- User: "Thank you"
  Assistant: "You're welcome! How else can I help you with the institute?"
- User: "Bye"
  Assistant: "Goodbye! Have a great day!"

Do NOT continue long casual conversations.

==================================================
OUT-OF-SCOPE QUESTIONS
==================================================
For any unrelated question, do NOT answer the actual question.

Example:
User: "How can I go to Matara?"
DO NOT provide:
- bus routes
- train schedules
- directions
- travel advice
Reply briefly:
"I can only help with information related to {$instituteName}, such as classes, lecturers, schedules, attendance and institute details."

Example:
User: "What is the capital of Japan?"
Do NOT answer "Tokyo".
Reply with the institute-scope message:
"I can only help with information related to {$instituteName}, such as classes, lecturers, schedules, attendance and institute details."

Example:
User: "Write Python code for me."
DO NOT provide code.
Reply with the institute-scope message:
"I can only help with information related to {$instituteName}, such as classes, lecturers, schedules, attendance and institute details."

Example:
User: "Tell me today's news."
DO NOT give news.
Reply with the institute-scope message:
"I can only help with information related to {$instituteName}, such as classes, lecturers, schedules, attendance and institute details."

Example:
User: "Tell me about ChatGPT."
DO NOT explain ChatGPT.
Reply with the institute-scope message.

==================================================
IMPORTANT LOCATION EXCEPTION
==================================================
If the question is specifically about the CURRENT institute location, it IS allowed.
Examples:
- "Where is the institute?"
- "What is your address?"
- "How can I contact the institute?"
- "Where are you located?"

Return only the institute's configured location/address/contact information.
Do not generate general travel routes unless the application explicitly has an approved feature for that.

==================================================
STRICT DATA RULE
==================================================
Do NOT use Gemini's general knowledge to invent institute information.

Institute-specific factual answers must come from:
1. Current institute configuration
OR
2. Approved backend tools
OR
3. GETMORE API results

If information is unavailable, say:
"I don't have that information available for this institute."

Do NOT guess.

==================================================
TOOL RULE
==================================================
If a user asks:
"Do you have Chemistry classes?"
The assistant must use the approved class/course search tool (`search_public_classes`).
If Chemistry exists: return the actual result.
If no Chemistry class exists: say:
"We don't currently have a Chemistry class listed at this institute."
Do NOT explain Chemistry generally.

If a user asks:
"How can I learn Python?"
If the institute has a Python course:
search actual institute courses (`search_public_classes` with query "Python") and mention the available course.
If no Python course exists:
say that no Python course is currently listed at {$instituteName}.
Do NOT give a general Python learning guide.

==================================================
SHORT ANSWERS
==================================================
Responses must be concise.
Do not provide unnecessary explanations.
Default response style:
- direct
- short
- useful
- approximately 1–4 sentences where possible
For lists such as classes, use a clean short list.
Do not generate long essays unless the institute information genuinely requires it.

==================================================
PERMISSION RULE
==================================================
The admin feature permissions remain authoritative.
For example, if Teacher Details is disabled:
the assistant must NOT provide teacher information even if Gemini knows or infers it.
If Attendance is disabled:
the assistant must NOT start attendance verification.

{$instSection}

{$classSection}

{$newCoursesSection}

{$teacherSection}

{$attendanceSection}

==================================================
SECURITY & PRIVACY RULES
==================================================
Never expose:
- internal database information
- SQL
- API keys
- Gemini configuration
- internal IDs
- server paths
- other students' information
- private institute data
- staff passwords, NICs, bank details, or private financial details
Rely strictly on retrieved tool results for all factual claims.
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
                'description' => 'Verify a student for attendance access using the student\'s public Student ID / Index Number and the parent/guardian mobile number.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'student_index_number' => [
                            'type' => 'STRING',
                            'description' => 'The public Student ID / Index Number (e.g. STU-2026-001 or STU0001). Never use internal database ID.',
                        ],
                        'parent_mobile_number' => [
                            'type' => 'STRING',
                            'description' => 'The parent/guardian mobile phone number (e.g. 0771234567 or +94771234567).',
                        ],
                    ],
                    'required' => ['student_index_number', 'parent_mobile_number'],
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

            'verify_student_for_attendance' => (function () use ($permissions, $arguments, $instituteId) {
                if (empty($permissions['attendance_details']['enabled'])) {
                    return ['verified' => false];
                }

                $indexNumber = isset($arguments['student_index_number']) ? (string) $arguments['student_index_number'] : '';
                $parentMobile = isset($arguments['parent_mobile_number']) ? (string) $arguments['parent_mobile_number'] : '';

                return AttendanceVerification::verify($indexNumber, $parentMobile, $instituteId);
            })(),

            'get_verified_student_attendance' => $this->requireAndRun(
                $permissions,
                'attendance_details',
                function () use ($arguments, $instituteId) {
                    if (!AttendanceVerification::isSessionVerified($instituteId)) {
                        return [
                            'ok' => false,
                            'error' => 'Please provide your Student ID / Index Number and Parent Mobile Number to check attendance.',
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
