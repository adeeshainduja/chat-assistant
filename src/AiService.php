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
        int $assistantId = 1
    ): string {
        $assistant = $this->repository->getAssistant($assistantId);

        if (!(bool) ($assistant['enabled'] ?? 0)) {
            return 'The AI assistant is currently unavailable.';
        }

        $permissions = $this->repository->getPermissions($assistantId);
        $tools = $this->buildTools($permissions);
        $contents = $this->buildContents($history, $message);

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $this->buildInstructions($assistant)],
                ],
            ],
            'contents' => $contents,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        /*
         * Gemini can request one or more functions.
         * We allow up to 4 tool rounds for one student message.
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
             * No tool call means Gemini has produced the final student answer.
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
                $result = $this->executeTool($call, $permissions);

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

    private function buildInstructions(array $assistant): string
    {
        $name = (string) $assistant['name'];
        $description = (string) ($assistant['description'] ?? '');
        $purpose = (string) ($assistant['purpose'] ?? '');

        return <<<PROMPT
You are {$name}, the student-facing AI assistant for the GETMORE tuition class system.

ADMIN DESCRIPTION:
{$description}

ADMIN PURPOSE:
{$purpose}

SECURITY AND ACCESS RULES:
1. You are speaking to an already authenticated student.
2. You may use only the function tools provided by the backend in this request.
3. If a category has no available tool, say that information is not available through the student assistant.
4. Never ask for, accept, or use a student ID to access another student's information.
5. Attendance tools always refer only to the currently authenticated student.
6. Class tools return only the currently authenticated student's enrolled classes.
7. Teacher information is limited to approved teacher information returned by the tool.
8. Never provide another student's information, staff financial information, salaries, revenue, internal reports, passwords, credentials, API keys, database structure, SQL, or internal system configuration.
9. Never invent database values. If a tool returns no matching record, say no matching information was found.
10. Do not infer that a student was absent merely because no attendance row exists. Say that no attendance record was found.
11. Reply in the same language used by the student where practical.
12. Keep answers clear and student-friendly.
PROMPT;
    }

    private function buildTools(array $permissions): array
    {
        $declarations = [];

        if (!empty($permissions['class_details']['enabled'])) {
            $declarations[] = [
                'name' => 'get_my_classes',
                'description' => 'Return enrolled class details for the authenticated student only.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new stdClass(),
                ],
            ];
        }

        if (!empty($permissions['attendance_details']['enabled'])) {
            $declarations[] = [
                'name' => 'get_my_attendance',
                'description' => 'Return attendance for the authenticated student only.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'date' => [
                            'type' => 'STRING',
                            'description' => 'Optional date string in YYYY-MM-DD format.',
                        ],
                    ],
                ],
            ];
        }

        if (!empty($permissions['teacher_details']['enabled'])) {
            $declarations[] = [
                'name' => 'get_teacher_details',
                'description' => 'Return approved teacher details associated with the authenticated student’s enrolled classes.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new stdClass(),
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

    private function executeTool(array $call, array $permissions): array
    {
        $name = (string) ($call['name'] ?? '');
        $arguments = is_array($call['arguments'] ?? null) ? $call['arguments'] : [];

        return match ($name) {
            'get_my_classes' => $this->requireAndRun(
                $permissions,
                'class_details',
                fn() => $this->connector->myClasses()
            ),

            'get_my_attendance' => $this->requireAndRun(
                $permissions,
                'attendance_details',
                fn() => $this->connector->myAttendance(
                    isset($arguments['date']) && is_string($arguments['date']) && trim($arguments['date']) !== ''
                        ? trim($arguments['date'])
                        : null
                )
            ),

            'get_teacher_details' => $this->requireAndRun(
                $permissions,
                'teacher_details',
                fn() => $this->connector->myTeachers()
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
                    : 'The approved data source could not be reached.',
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
