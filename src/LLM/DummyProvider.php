<?php

namespace AskMyDB\Laravel\LLM;

use AskMyDB\Laravel\Contracts\LLMProvider;

class DummyProvider implements LLMProvider
{
    public function generateSql(string $prompt, string $schemaText): string
    {
        // A trivial heuristic: list first table if available
        if (preg_match('/Table:\s*(\w+)/', $schemaText, $m)) {
            $table = $m[1];
            return "SELECT * FROM {$table} LIMIT 25";
        }
        return 'SELECT 1 as result LIMIT 1';
    }
}
