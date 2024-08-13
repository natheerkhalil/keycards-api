<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Auth;

use App\Models\Feedback;

class FeedbackController extends Controller
{
    public function create(Request $request)
    {
        $user = Auth::user();

        $banned_feedback = $user->banned_feedback;

        if ($banned_feedback) {
            return response()->json([
                "message" => "You are banned from providing feedback."
            ], 423);
        }

        try {
            $request->validate([
                "data" => "required|string|max:9999",
            ]);

            // SAVE DATA TO FEEDBACK TABLE
            $username = $user->username;

            Feedback::create([
                "username" => $username,
                "data" => $request->data
            ]);

            return response()->json([
                "message" => "Feedback saved successfully."
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                "message" => "An error occurred while saving feedback: " . $e->getMessage()
            ], 500);
        }
    }
}
