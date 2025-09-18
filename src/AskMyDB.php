<?php

namespace AskMyDB\Laravel;

use AskMyDB\Laravel\Contracts\LLMProvider;
use AskMyDB\Laravel\LLM\DummyProvider;
use AskMyDB\Laravel\LLM\OllamaProvider;
use AskMyDB\Laravel\LLM\OpenAIProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AskMyDB
{
    /**
     * Convert natural language to SQL and execute it.
     *
     * @return array{0:string,1:array}
     */
    public function ask(string $prompt): array
    {
        $schemaText = $this->getSchemaText();
        $provider = $this->resolveProvider();
        $sql = trim($provider->generateSql($prompt, $schemaText));

        $sql = $this->sanitizeSql($sql);

        $conn = $this->getConnection();
        $results = $conn->select($sql);
        $resultsArray = json_decode(json_encode($results), true) ?: [];

        return [$sql, $resultsArray];
    }

    /**
     * Return database schema as an associative array.
     */
    public function getSchemaJson(): array
    {
        $connection = $this->getConnection();
        $driver = $connection->getDriverName();

        $schema = [];

		if ($driver === 'sqlite') {
            $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $tableRow) {
                $tableName = $tableRow->name;
                $columns = $connection->select("PRAGMA table_info('$tableName')");
                $schema[$tableName] = array_map(function ($col) {
                    return [
                        'name' => $col->name,
                        'type' => $col->type,
                        'notnull' => (bool) $col->notnull,
                        'default' => $col->dflt_value,
                        'pk' => (bool) $col->pk,
                    ];
                }, $columns);
            }
            return $this->limitSchema($schema);
        }

		// MySQL: restrict to current database only
		if ($driver === 'mysql') {
			$database = $connection->getDatabaseName();
			$tables = $connection->select(
				"SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type='BASE TABLE' ORDER BY table_name",
				[$database]
			);
			foreach ($tables as $tableRow) {
				$tableName = $tableRow->table_name ?? $tableRow->TABLE_NAME ?? null;
				if (!$tableName) {
					continue;
				}
				$columns = $connection->select(
					"SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position",
					[$database, $tableName]
				);
				$schema[$tableName] = array_map(function ($col) {
					$name = $col->column_name ?? $col->COLUMN_NAME ?? null;
					$type = $col->data_type ?? $col->DATA_TYPE ?? null;
					$nullable = ($col->is_nullable ?? $col->IS_NULLABLE ?? 'YES') === 'YES';
					$default = $col->column_default ?? $col->COLUMN_DEFAULT ?? null;
					return [
						'name' => $name,
						'type' => $type,
						'notnull' => !$nullable,
						'default' => $default,
						'pk' => false,
					];
				}, $columns);
			}
			return $this->limitSchema($schema);
		}

		// Postgres: restrict to current schemas only (search_path)
		if ($driver === 'pgsql') {
			$tables = $connection->select(
				"SELECT table_name FROM information_schema.tables WHERE table_schema = ANY (current_schemas(false)) AND table_type='BASE TABLE' ORDER BY table_name"
			);
			foreach ($tables as $tableRow) {
				$tableName = $tableRow->table_name ?? $tableRow->TABLE_NAME ?? null;
				if (!$tableName) {
					continue;
				}
				$columns = $connection->select(
					"SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema = ANY (current_schemas(false)) AND table_name = ? ORDER BY ordinal_position",
					[$tableName]
				);
				$schema[$tableName] = array_map(function ($col) {
					$name = $col->column_name ?? $col->COLUMN_NAME ?? null;
					$type = $col->data_type ?? $col->DATA_TYPE ?? null;
					$nullable = ($col->is_nullable ?? $col->IS_NULLABLE ?? 'YES') === 'YES';
					$default = $col->column_default ?? $col->COLUMN_DEFAULT ?? null;
					return [
						'name' => $name,
						'type' => $type,
						'notnull' => !$nullable,
						'default' => $default,
						'pk' => false,
					];
				}, $columns);
			}
			return $this->limitSchema($schema);
		}

		// Generic Information Schema fallback
		$tables = $connection->select("SELECT table_name FROM information_schema.tables WHERE table_schema NOT IN ('information_schema','pg_catalog','mysql','performance_schema','sys')");
        foreach ($tables as $tableRow) {
            $tableName = $tableRow->table_name ?? $tableRow->TABLE_NAME ?? null;
            if (!$tableName) {
                continue;
            }
            $columns = $connection->select("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = ?", [$tableName]);
            $schema[$tableName] = array_map(function ($col) {
                $name = $col->column_name ?? $col->COLUMN_NAME ?? null;
                $type = $col->data_type ?? $col->DATA_TYPE ?? null;
                $nullable = ($col->is_nullable ?? $col->IS_NULLABLE ?? 'YES') === 'YES';
                $default = $col->column_default ?? $col->COLUMN_DEFAULT ?? null;
                return [
                    'name' => $name,
                    'type' => $type,
                    'notnull' => !$nullable,
                    'default' => $default,
                    'pk' => false,
                ];
            }, $columns);
        }

        return $this->limitSchema($schema);
    }

    /**
     * Return database schema as human-readable text.
     */
    public function getSchemaText(): string
    {
        $schema = $this->getSchemaJson();
        $lines = [];
        foreach ($schema as $tableName => $columns) {
            $lines[] = "Table: {$tableName}";
            foreach ($columns as $col) {
                $null = $col['notnull'] ? 'NOT NULL' : 'NULL';
                $default = $col['default'] !== null ? " DEFAULT(" . (string) $col['default'] . ")" : '';
                $pk = $col['pk'] ? ' PRIMARY KEY' : '';
                $lines[] = "  - {$col['name']} {$col['type']} {$null}{$default}{$pk}";
            }
        }
        return implode("\n", $lines);
    }

    protected function limitSchema(array $schema): array
    {
        $maxTables = (int) config('askmydb.introspection.max_tables', 50);
        $maxColumns = (int) config('askmydb.introspection.max_columns', 60);

        // Trim number of tables
        if (count($schema) > $maxTables) {
            $schema = array_slice($schema, 0, $maxTables, true);
        }

        // Trim columns per table
        foreach ($schema as $table => $cols) {
            if (count($cols) > $maxColumns) {
                $schema[$table] = array_slice($cols, 0, $maxColumns);
            }
        }

        return $schema;
    }

    protected function sanitizeSql(string $sql): string
    {
        // Only allow SELECT queries for safety.
        $normalized = ltrim(Str::lower($sql));
        if (!Str::startsWith($normalized, 'select')) {
            // Attempt to extract a SELECT statement inside code fences or text
            if (preg_match('/select[\s\S]*?;?/i', $sql, $m)) {
                $sql = $m[0];
            } else {
                // Fallback to a harmless query
                return $this->fallbackSelect();
            }
        }

        // Ensure there is a reasonable LIMIT to avoid huge responses
        if (!preg_match('/\blimit\b/i', $sql)) {
            $sql = rtrim($sql, "; \t\n\r\0\x0B") . ' LIMIT 100';
        }

        return $sql;
    }

    protected function fallbackSelect(): string
    {
        $driver = $this->getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return "SELECT name as table_name FROM sqlite_master WHERE type='table' LIMIT 10";
        }
        return "SELECT table_name FROM information_schema.tables LIMIT 10";
    }

    protected function resolveProvider(): LLMProvider
    {
        $driver = config('askmydb.provider', 'dummy');
        return match ($driver) {
            'openai' => new OpenAIProvider(
                (string) config('askmydb.openai.api_key', ''),
                (string) config('askmydb.openai.base_url', 'https://api.openai.com/v1'),
                (string) config('askmydb.openai.model', 'gpt-4o-mini'),
                (float) config('askmydb.openai.temperature', 0.2)
            ),
            'ollama' => new OllamaProvider(
                (string) config('askmydb.ollama.base_url', 'http://localhost:11434'),
                (string) config('askmydb.ollama.model', 'llama3.1')
            ),
            default => new DummyProvider(),
        };
    }

    protected function getConnection()
    {
        $name = config('askmydb.connection');
        return $name ? DB::connection($name) : DB::connection();
    }
}
