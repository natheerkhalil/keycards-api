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

    // CREATE
    public function createMany(Request $request)
    {
        try {
            $request->validate([
                "cards" => "required|array|max:500",
                "cards.*.q" => "required|string|max:9999|min:1",
                "cards.*.a" => "required|string|max:9999|min:1",
                "folder" => "required|exists:folders,id",
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();

            $cards = [];

            foreach ($request->cards as $card) {
                $cards[] = [
                    "q" => trim($card["q"]),
                    "a" => trim($card["a"]),
                    "folder" => $folder->id,
                    "creator" => $user->username,
                    "status" => "0",
                    "created_at" => now(),
                    "updated_at" => now(),
                ];
            }

            $data = Card::insert($cards);

            $insertedCards = Card::where('folder', $folder->id)
                ->where('creator', $user->username)
                ->orderBy('created_at', 'desc')
                ->take(count($cards))
                ->get();


            return response()->json([
                "cards" => $insertedCards->map(function ($card) {
                    return [
                        "id" => $card->id,
                        "q" => $card->q,
                        "a" => $card->a,
                        "folder" => $card->folder,
                        "status" => $card->status,
                    ];
                })
            ]);

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
                "cards" => "required|array|max:9999",
            ]);

            $user = Auth::user();

            // delete all cards in the provided array
            foreach ($request->cards as $card_id) {
                $card = Card::where("creator", $user->username)->where("id", $card_id)->first();
                if ($card)
                    $card->delete();
            }

            // return success message
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
                "status" => "required|in:0,1,2",
                "cards" => "required|array|max:9999",
            ]);

            $user = Auth::user();

            // update all cards in the provided array
            foreach ($request->cards as $card_id) {
                $card = Card::where("creator", $user->username)->where("id", $card_id)->first();

                if ($card)
                    $card->update(["status" => $request->status]);
            }

            // return success message
            return response()->json(["message" => "Card statuses updated successfully", "cards" => $request->cards]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // MOVE //
    public function move(Request $request)
    {
        try {
            $request->validate([
                "cards" => "required|array|max:9999",
                "folder" => "required|exists:folders,id",
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();

            // move all cards in the provided array
            foreach ($request->cards as $card_id) {
                $card = Card::where("creator", $user->username)->where("id", $card_id)->first();
                if ($card) {
                    $card->update(["folder" => $folder->id]);
                    $card->save();
                }
            }

            // return success message
            return response()->json(["message" => "Cards moved successfully"]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }
}
