<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\FolderController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\DataController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('throttle:30,1')->group(function () {
    // authorisation
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('throttle:50,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
});

Route::group(["middleware" => ["auth:sanctum", "throttle:70,1"]], function () {
    // get all data
    Route::post("/all", [DataController::class, 'all']);

    // folder
    Route::post("/folder/create", [FolderController::class, 'create']);
    Route::post("/folder/read", [FolderController::class,'read']);
    Route::post("/folder/update", [FolderController::class, 'update']);
    Route::post("/folder/delete", [FolderController::class, 'delete']);

    Route::post("/folder/move", [FolderController::class,'move']);
    Route::post("/folder/list", [FolderController::class, 'list']);
    Route::post("/folder/children", [FolderController::class, 'children']);
    Route::post("/folder/cards", [FolderController::class, 'cards']);
    
    // card
    Route::post("/card/create", [CardController::class, 'create']);
    Route::post("/card/read", [CardController::class, 'read']);
    Route::post("/card/update", [CardController::class, 'update']);
    Route::post("/card/delete", [CardController::class, 'delete']);

    Route::post("/card/list", [CardController::class, 'list']);
    Route::post("/card/mark", [CardController::class,'mark']);

    // share
    Route::post("/share/create", [ShareController::class, 'create']);
    Route::post("/share/read", [ShareController::class, 'read']);
    Route::post("/share/update", [ShareController::class, 'update']);
    Route::post("/share/delete", [ShareController::class, 'delete']);

    Route::post("/share/accept", [ShareController::class, 'accept']);
    Route::post("/share/decline", [ShareController::class,'decline']);

    Route::post("/share/listSent", [ShareController::class, 'listSent']);
    Route::post("/share/listReceived", [ShareController::class, 'listReceived']);

    Route::post("/share/preview", [ShareController::class, 'preview']);
});

Route::middleware('throttle:60,1')->group(function () {
    // reset password
    Route::post("/account/send-reset-password-email", [AuthController::class, 'sendResetPasswordEmail']);
    Route::post("/account/change-password", [AuthController::class, 'changePassword']);
});