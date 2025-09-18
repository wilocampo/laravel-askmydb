<?php

namespace AskMyDB\Laravel\LLM;

use AskMyDB\Laravel\Contracts\LLMProvider;
use Illuminate\Support\Facades\Http;

class OpenAIProvider implements LLMProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected string $model,
        protected float $temperature = 0.2
    ) {
    }

    public function generateSql(string $prompt, string $schemaText): string
    {
        $system = self::buildSystemPrompt();
        $user = self::buildSqlPrompt($prompt, $schemaText);

        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->post($url, [
            'model' => $this->model,
            'temperature' => $this->temperature,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        if (!$response->ok()) {
            return 'SELECT 1 LIMIT 1';
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Extract SQL from code fences if present
        if (preg_match('/```sql\s*([\s\S]*?)```/i', $content, $m)) {
            return trim($m[1]);
        }
        return trim($content) ?: 'SELECT 1 LIMIT 1';
    }

    public static function buildSystemPrompt(): string
    {
        return 'You are a SQL generator. Output only a valid SQL SELECT statement for the user\'s request based on the provided schema. Never modify data. Prefer concise queries with LIMIT.';
    }

    public static function buildSqlPrompt(string $prompt, string $schemaText): string
    {
        return "Schema:\n" . $schemaText . "\n\nUser request: " . $prompt . "\n\nReturn only SQL. Use table and column names exactly as shown.";
    }
}
