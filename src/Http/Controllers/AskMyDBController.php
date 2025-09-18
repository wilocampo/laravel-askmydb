<?php

namespace AskMyDB\Laravel\Http\Controllers;

use AskMyDB\Laravel\AskMyDB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AskMyDBController extends Controller
{
	public function index()
	{
		return view('askmydb::index');
	}

	public function ask(Request $request)
	{
		$validated = $request->validate([
			'prompt' => ['required', 'string', 'max:2000'],
		]);
		[$sql, $result] = app(AskMyDB::class)->ask($validated['prompt']);
		return response()->json([
			'sql' => $sql,
			'result' => $result,
		]);
	}

	public function schemaJson()
	{
		return response()->json(app(AskMyDB::class)->getSchemaJson());
	}
}
