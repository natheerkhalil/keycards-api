<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Folder;

use Illuminate\Support\Facades\Auth;

class CardController extends Controller
{
    // C . R . U . D //

    // CREATE
    public function create(Request $request)
    {
        try {
            $request->validate([
                "q" => "required|string|max:9999|min:1",
                "a" => "required|string|max:9999|min:1",
                "folder" => "required|exists:folders,id",
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();

            $request->q = trim($request->q);
            $request->a = trim($request->a);

            $card = [
                "q" => $request->q,
                "a" => $request->a,
                "folder" => $folder->id,
                "creator" => $user->username,
                "status" => "0",
                "created_at" => now(),
                "updated_at" => now(),
            ];

            $data = Card::create($card);

            return response()->json(["id" => $data->id]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // READ
    public function read(Request $request)
    {
        try {
            $request->validate([
                "id" => "required|exists:cards,id",
            ]);

            $user = Auth::user();

            $card = Card::where("creator", $user->username)->where("id", $request->id)->firstOrFail();

            return response()->json(["data" => $card]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // UPDATE
    public function update(Request $request)
    {
        try {
            $request->validate([
                "id" => "required|exists:cards,id",
                "q" => "required|string|max:9999|min:1",
                "a" => "required|string|max:9999|min:1",
            ]);

            $user = Auth::user();

            $card = Card::where("creator", $user->username)->where("id", $request->id)->firstOrFail();

            $card->q = trim($request->q);
            $card->a = trim($request->a);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // DELETE
    public function delete(Request $request)
    {
        try {
            $request->validate([
                "id" => "required|exists:cards,id",
            ]);

            $user = Auth::user();

            $card = Card::where("creator", $user->username)->where("id", $request->id)->firstOrFail();

            $card->delete();

            return response()->json(["message" => "Card deleted successfully"]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // END C . R . U . D //



    // LIST //
    public function list(Request $request)
    {
        try {
            $request->validate([
                "folder" => "required|string|exists:folders,id",
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();
            $cards = $folder->cards()->get();

            return response()->json(["data" => $cards]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // MARK //
    public function mark(Request $request)
    {
        try {
            $request->validate([
                "status" => "required|in:-1,0,1",
                "id" => "required|exists:cards,id",
            ]);

            $user = Auth::user();

            $card = Card::where("creator", $user->username)->where("id", $request->id)->firstOrFail();

            $card->status = $request->status;
            $card->reviewed_at = now();
            $card->save();

            return response()->json(["message" => "Card status updated successfully"]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }
}
