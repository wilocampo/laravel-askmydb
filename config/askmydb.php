<?php

return [
	// Provider driver: dummy | openai | ollama
	'provider' => env('ASKMYDB_PROVIDER', 'dummy'),

	// Optional Laravel DB connection name to use (null = default connection)
	'connection' => env('ASKMYDB_CONNECTION', null),

	'openai' => [
		'api_key' => env('OPENAI_API_KEY', ''),
		'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
		'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
		'temperature' => env('OPENAI_TEMPERATURE', 0.2),
	],

	'ollama' => [
		'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
		'model' => env('OLLAMA_MODEL', 'llama3.1'),
	],

	// Limits to keep prompts within model context
	'introspection' => [
		'max_tables' => (int) env('ASKMYDB_MAX_TABLES', 50),
		'max_columns' => (int) env('ASKMYDB_MAX_COLUMNS', 60),
	],
];
