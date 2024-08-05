<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['status' => 'Email already verified'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['status' => 'Verification email sent'], 200);
    }
}
