### Laravel Ask-My-DB

Query your database using natural language via LLMs. This package turns plain-English prompts into safe SQL SELECT statements and returns the results. If you need the fully working demo, get it here https://github.com/wilocampo/laravel-askmydb-full

Background and attribution
- This is a Laravel/PHP reimplementation inspired by the original Python project: [Ask-My-DB (Python)](https://github.com/Msalways/Ask-My-DB).
- Not a fork; built from scratch for Laravel 12 with provider-agnostic LLMs and a simple demo UI.

## Features
- Natural language to SQL: Ask questions like "top 10 best sellers this month".
- Provider-agnostic: OpenAI/OpenRouter (OpenAI-compatible) and Ollama supported.
- Safe by default: Only SELECT queries are executed; LIMIT is enforced.
- Configurable DB target: Use default or a named Laravel connection.
- Schema introspection with limits: Keeps prompts within model context size.
- Demo UI included: Prompt input, SQL preview, JSON result, and charts.
- Charts: Bar, Line, Area, Pie, Donut, Scatter with auto-render and axis selectors.
- Simple integration: Facade API for server-side usage.

## Why it helps
- Reduce BI bottlenecks: Non-technical teammates can answer questions quickly.
- Faster prototyping: Product and data teams can iterate without writing SQL.
- Safe exploration: Read-only execution with sanitization minimizes risk.
- Flexible deployment: Works with cloud LLMs or local models via Ollama.

## Use cases
- Ad-hoc analytics: “What are yesterday’s top 10 items by quantity?”
- Customer support insights: “Show the last 50 orders for customer X.”
- Inventory ops: “Which stores are low on stock for SKU Y?”
- Finance views: “Monthly sales by category for the last 6 months.”
- Engineering dashboards: Quick queries in staging/test without building UIs.

## Requirements
- PHP ^8.2
- Laravel ^12.0

## Installation (local path package)
1) In your app `composer.json`, add the path repository and require the package (example):

```json
{
  "require": {
    "askmydb/laravel-askmydb": "*"
  },
  "repositories": [
    {
      "type": "path",
      "url": "packages/askmydb/laravel-askmydb",
      "options": { "symlink": true }
    }
  ]
}
```

2) Install and publish config:
```bash
composer update askmydb/laravel-askmydb
php artisan vendor:publish --tag=askmydb-config
```

3) Clear caches after changing `.env` or config:
```bash
php artisan config:clear && php artisan route:clear && php artisan optimize:clear
```

## Configuration
Set these in `.env` as needed. All values are read from `config/askmydb.php`.

```env
# Which provider to use: dummy | openai | ollama
ASKMYDB_PROVIDER=openai

# Optional: explicitly choose a Laravel DB connection name
# (defaults to the app's default connection)
ASKMYDB_CONNECTION=

# OpenAI-compatible providers (OpenAI or OpenRouter)
OPENAI_API_KEY=YOUR_KEY
# For OpenAI use https://api.openai.com/v1
# For OpenRouter use https://openrouter.ai/api/v1
OPENAI_BASE_URL=https://openrouter.ai/api/v1

# Model id must match your provider (examples)
# - OpenAI: gpt-4o-mini
# - OpenRouter: openai/gpt-4o-mini, google/gemini-1.5-flash, anthropic/claude-3.5-sonnet, etc.
OPENAI_MODEL=openai/gpt-4o-mini

# Temperature must be numeric (no quotes)
OPENAI_TEMPERATURE=0.2

# Ollama (local)
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.1

# Prompt size controls to avoid context-limit errors
ASKMYDB_MAX_TABLES=50
ASKMYDB_MAX_COLUMNS=60
```

Notes:
- To use OpenRouter, set `OPENAI_BASE_URL` to `https://openrouter.ai/api/v1` and keep using the OpenAI-compatible settings above.
- Ensure `OPENAI_TEMPERATURE` is a number (e.g., `0.2`), not a quoted string.

## Usage
- UI demo: visit `/askmydb` to try a simple page.
- API: `POST /askmydb/ask` with JSON `{ "prompt": "..." }` → `{ sql, result }`.
- Schema: `GET /askmydb/schema.json` for the introspected schema.

In PHP code via the facade:
```php
use AskMyDB\Laravel\Facades\AskMyDB;
[$sql, $rows] = AskMyDB::ask('from users list the 10 latest id and email');
```

In Tinker:
```bash
php artisan tinker --execute="dump(AskMyDB\\Laravel\\Facades\\AskMyDB::ask('from users show 5 most recent'));"
```

## How it works (high level)
- The package introspects your DB schema and builds a compact text summary.
- The selected LLM provider receives the schema + your prompt and returns SQL.
- The SQL is sanitized to only allow SELECT and ensure a LIMIT is present.
- The sanitized SQL is executed on the configured Laravel connection; rows are returned.

## Security and safeguards
- Only SELECT queries are executed. Any non-SELECT is replaced by a harmless fallback query.
- A LIMIT is enforced if the model forgets to include one.
- You can scope the connection with `ASKMYDB_CONNECTION` to a read-only replica.

## Troubleshooting
- Still getting `SELECT 1 LIMIT 1`:
  - This is a fallback when the provider returns an error or empty content. Verify `.env` values, model id, and that your key is valid.
  - Check `storage/logs/laravel.log` for HTTP errors. Then run:
    - `php artisan config:clear && php artisan optimize:clear`

- OpenRouter 400 "Expected number, received string":
  - Ensure `OPENAI_TEMPERATURE` is a number like `0.2` (without quotes) and clear config cache.

- 400 "maximum context length ... requested ...":
  - Reduce prompt size by tightening introspection:
    - Increase/decrease `ASKMYDB_MAX_TABLES` or `ASKMYDB_MAX_COLUMNS` as needed.
  - Or choose a model with a larger context window.

- "Base table or view not found":
  - The model guessed a table name. Confirm real names at `/askmydb/schema.json` and include them in your prompt, e.g.,
    - "From table `users`, list the latest 10 users with id and email."

- CSRF/419 errors testing the UI:
  - Routes are registered in the `web` middleware group. Ensure you access the app via the correct `APP_URL` and domain.

## Provider guidance
- OpenAI/OpenRouter: Prefer small, capable models with good reasoning for SQL (e.g., `gpt-4o-mini`). On OpenRouter, use their model ids (e.g., `openai/gpt-4o-mini`, `google/gemini-1.5-flash`).
- Ollama: Any chat model can work. `llama3.1` or `qwen2.5-coder` variants often produce good SQL.

## Demo UI
The demo page at `/askmydb` is a Tailwind-based screen showing the prompt, generated SQL, JSON results, and a chart card.

- Auto-rendered charts supported: Bar, Line, Area (line+fill), Pie, Donut, Scatter.
- Pick X and Y columns; charts re-render automatically on changes.
- Large screens show results and chart side-by-side.
- Replace with your design system (e.g., TailAdmin) in your host app.

Tips:
- Pie/Donut use the first selected Y series.
- Scatter requires at least two numeric Y series (X1 vs Y1).

## Versioning
- Current package version: `0.1.0`.

## License
MIT
