<?php

use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ShareController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('throttle:30,1')->group(function () {
    // authorisation
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('throttle: 200,1')->group(function () {
    Route::post('/validation/login', [AuthController::class, 'validateLogin']);
    Route::post('/validation/register', [AuthController::class, 'validateRegister']);

    // lyrics
    Route::post('/video/lyrics', [VideoController::class, 'lyrics']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('throttle:1,1')->group(function () {
        // videos
        Route::post('/video/save', [VideoController::class, 'save']);
        Route::post('/video/fetch', [VideoController::class, 'fetch']);
        Route::post('/video/load', [VideoController::class, 'load']);
        Route::post('/video/fav', [VideoController::class, 'fav']);
        Route::post('/video/all', [VideoController::class, 'all']);
        Route::post('/video/favs', [VideoController::class, 'favs']);
        Route::post('/video/delete', [VideoController::class, 'delete']);
        Route::post('/video/random', [VideoController::class, 'random']);
        Route::post('/video/playlist', [VideoController::class, 'playlist']);

        // playlist
        Route::post('/playlist/create', [PlaylistController::class, 'create']);
        Route::post('/playlist/update', [PlaylistController::class, 'update']);
        Route::post('/playlist/delete', [PlaylistController::class, 'delete']);
        Route::post('/playlist/videos', [PlaylistController::class, 'delete']);
        Route::post('/playlist/list', [PlaylistController::class, 'list']);
        Route::post('/playlist/add', [PlaylistController::class, 'add']);
        Route::post('/playlist/remove', [PlaylistController::class, 'remove']);

        // shares
        Route::post('/share/create', [ShareController::class, 'create']);
        Route::post('/share/respond', [ShareController::class, 'respond']);
        Route::post('/share/list', [ShareController::class, 'list']);

        // account
        Route::post('/account/delete', [AuthController::class, 'deleteAccount']);
        Route::post('/account/is-verified', [AuthController::class, 'isVerified']);
        Route::post('/account/send-verification-email', [AuthController::class, 'sendVerificationEmail']);
        Route::post('/account/verify-token', [AuthController::class, 'verifyToken']);
        Route::post('/account/send-email-change-email', [AuthController::class, 'sendEmailChangeEmail']);
        Route::post('/account/change-email', [AuthController::class, 'changeEmail']);
    });
});

Route::middleware('throttle:60,1')->group(function () {
    // reset password
    Route::post("/account/send-reset-password-email", [AuthController::class, 'sendResetPasswordEmail']);
    Route::post("/account/change-password", [AuthController::class, 'changePassword']);
});


/*// csrf token
Route::middleware(['web'])->get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});



// testing
Route::get('/test', function () {
    return response()->json(['message' => 'new test message'], 200);
});

Route::get('/test/email', [TestController::class, "testEmail"]);*/