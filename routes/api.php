<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::any('/xcube', function (Request $request) { return $request->wantsJson() ? response()->json(null,403) : abort('403'); });
// Metodo post è prioritario rispetto a any
Route::post('/xcube', 'ScontrinoController@storeData');
Route::get('/xcube/scontriniNuovi', 'ScontrinoController@getNuovi');
Route::get('/xcube/scontriniStampati', 'ScontrinoController@getDaInviare');
Route::post('/xcube/scontriniStampati', 'ScontrinoController@setStampati');
Route::get('/xcube/scontriniInviati', 'ScontrinoController@getInviati');
Route::post('/xcube/scontriniInviati', 'ScontrinoController@setInviati');

