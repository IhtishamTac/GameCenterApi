<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\UserController;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::prefix('v1')->group(function(){
    Route::prefix('auth')->group(function(){
        Route::post('signup', [AuthController::class, 'signup']);
        Route::post('signin', [AuthController::class, 'signin']);
        Route::post('signout', [AuthController::class, 'signout'])->middleware('auth:sanctum');
    });

    //disini kita pakai middleware user blocked bersamaan dengan middleware dari auth sanctum
    Route::middleware(['auth:sanctum', 'user.blocked'])->group(function(){
        Route::get('admins', [UserController::class, 'adminlist']);
        Route::post('users', [UserController::class, 'addUser']);

        Route::put('users/{id}', [UserController::class, 'updateUser']);
        Route::delete('users/{id}', [UserController::class, 'deleteUser']);
        Route::post('users/{id}/block', [UserController::class, 'blockUser']);
        Route::delete('users/{id}/unblock', [UserController::class, 'unblockUser']);

        Route::get('users', [UserController::class, 'userlist']);
        Route::get('usersid', [UserController::class, 'userlistid']);
        Route::get('users/{username}', [UserController::class, 'userdetail']);

        Route::get('games', [GamesController::class, 'listgames']);
        Route::post('games', [GamesController::class, 'postgame']);
        Route::get('games/{slug}', [GamesController::class, 'demogame']);
        Route::put('games/{slug}', [GamesController::class, 'updategame']);
        Route::delete('games/{slug}', [GamesController::class, 'deletegame']);


        Route::get('games/{slug}/scores', [GamesController::class, 'scoresList']);
        Route::post('games/{slug}/scores', [GamesController::class, 'addScore']);
        Route::post('games/{slug}/upload', [GamesController::class, 'uploadgame']);
    });
});
Route::fallback(function() {
    return response()->json([
        'status' => 'not-found',
        'message' => 'Not found'
    ], 404);
});
