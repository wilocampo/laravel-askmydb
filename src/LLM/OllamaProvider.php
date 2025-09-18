<?php

namespace AskMyDB\Laravel\LLM;

use AskMyDB\Laravel\Contracts\LLMProvider;
use Illuminate\Support\Facades\Http;

class OllamaProvider implements LLMProvider
{
    public function __construct(
        protected string $baseUrl,
        protected string $model
    ) {
    }

    public function generateSql(string $prompt, string $schemaText): string
    {
        $system = OpenAIProvider::buildSystemPrompt();
        $user = OpenAIProvider::buildSqlPrompt($prompt, $schemaText);

        $url = rtrim($this->baseUrl, '/') . '/api/chat';

        $response = Http::post($url, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'stream' => false,
        ]);

        if (!$response->ok()) {
            return 'SELECT 1 LIMIT 1';
        }

        $data = $response->json();
        $content = $data['message']['content'] ?? '';
        if (preg_match('/```sql\s*([\s\S]*?)```/i', $content, $m)) {
            return trim($m[1]);
        }
        return trim($content) ?: 'SELECT 1 LIMIT 1';
    }
}
