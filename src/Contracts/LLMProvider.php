<?php

namespace AskMyDB\Laravel\Contracts;

interface LLMProvider
{
    /**
     * Generate a SQL query string for the given prompt and schema context.
     */
    public function generateSql(string $prompt, string $schemaText): string;
}
