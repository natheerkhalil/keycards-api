<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Auth;

use App\Models\Feedback;

class FeedbackController extends Controller
{
    public function create(Request $request) {
        $request->validate([
            "data" => "required|string|max:9999",
        ]);
        
        // SAVE DATA TO FEEDBACK TABLE
        $user = Auth::user();

        $username = $user->username;

        Feedback::create([
            "username" => $username,
            "data" => $request->data
        ]);

        return response()->json([
            "message" => "Feedback saved successfully."
        ], 200);
    }
}
