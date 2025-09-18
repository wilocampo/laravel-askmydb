<?php

namespace AskMyDB\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array ask(string $prompt)
 * @method static array getSchemaJson()
 * @method static string getSchemaText()
 */
class AskMyDB extends Facade
{
	protected static function getFacadeAccessor()
	{
		return 'askmydb';
	}
}
