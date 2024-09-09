<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use App\Models\Folder;

class ShareController extends Controller
{
    // C . R . U . D //

    // CREATE
    public function create(Request $request)
    {
        try {
            $request->validate([
                "folder" => "required|string|exists:folders,id",
                "receiver" => "required|string|exists:users,username",
                "view_only" => "boolean|required",
                
                "cards" => "boolean|required",
                "children" => "boolean|required"
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();

            if ($user->username == $request->receiver) {
                return response()->json(["error" => "You can't share a folder with yourself."], 400);
            }

            $share = Folder::where("sharer", $user->username)->where("receiver", $request->receiver)->where("folder", $folder->id)->first();

            if ($share) {
                return response()->json(["error" => "Folder already shared with this receiver."], 400);
            }

            Share::create([
                "sharer" => $user->username,
                "receiver" => $request->receiver,
                "folder" => $folder->id,
                "view_only" => $request->view_only
            ]);

            return response()->json(["message" => "Folder shared successfully."], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // READ
    public function read(Request $request) {
        try {
            $request->validate([
                "share" => "required|integer|exists:shares,id",
            ]);

            $user = Auth::user();

            $share = Share::where("id", $request->share)->where("receiver", $user->username)->where("accepted", false)->first();

            if (!$share) 
                $share = Share::where("id", $request->share)->where("sender", $user->username)->firstOrFail();

            return response()->json($share, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // UPDATE
    public function update(Request $request) {
        try {
            $request->validate([
                "share" => "required|integer|exists:shares,id",
                "view_only" => "boolean|required",

                "cards" => "boolean|required",
                "children" => "boolean|required"
            ]);

            $user = Auth::user();

            $share = Share::where("id", $request->share)->where("sharer", $user->username)->where("accepted", false)->firstOrFail();

            $share->view_only = $request->view_only;
            $share->cards = $request->cards;
            $share->children = $request->children;
            $share->save();

            return response()->json(["message" => "Folder share updated successfully."], 200);
        }
    }

    // DELETE
    public function delete(Request $request) {
        try {
            $request->validate([
                "share" => "required|integer|exists:shares,id",
            ]);

            $user = Auth::user();

            $share = Share::where("id", $request->share)->where("sender", $user->username)->where("accepted", false)->firstOrFail();

            $share->delete();

            return response()->json(["message" => "Folder share deleted successfully."], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // END C . R . U . D //


    // ACCEPT & DECLINE //
    public function accept(Request $request)
    {
        try {
            $request->validate([
                "share" => "required|integer|exists:shares,id",
            ]);

            $user = Auth::user();

            $share = Share::where("id", $request->share)->where("receiver", $user->username)->where("accepted", false)->where("view_only", false)->firstOrFail();

            $share->accepted = true;
            $share->save();

            $folder = Folder::where("id", $share->folder)->firstOrFail();

            Folder::create([
                "creator" => $folder->creator,
                "name" => $folder->name,
                "parent_folder" => null,
            ]);

            return response()->json(["message" => "Share accepted and folder copied."], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function decline(Request $request) {
        try {
            $request->validate([
                "share" => "required|integer|exists:shares,id",
            ]);

            $user = Auth::user();

            $share = Share::where("id", $request->share)->where("receiver", $user->username)->where("accepted", false)->firstOrFail();

            $share->delete();

            return response()->json(["message" => "Share declined."], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // PREVIEW //
    public function preview(Request $request)
    {
        try {
            $request->validate([
                "share" => "required|integer|exists:shares,id",
            ]);

            $user = Auth::user();

            $share = Share::where("id", $request->share)->where("receiver", $user->username)->where("accepted", false)->first();

            if (!$share) {
                $share = Share::where("id", $request->share)->where("receiver", $user->username)->where("view_only", true)->firstOrFail();
            }

            $folder = Folder::where("id", $share->folder)->firstOrFail();

            $cards = $folder->cards()->get();
            $children = $folder->children()->get();

            $data = [];

            $data["folder"] = $folder;

            if ($share->cards)
                $data["cards"] = $cards;
            if ($share->children)
                $data["children"] = $children;

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // LIST //
    public function listReceived(Request $request) {
        $user = Auth::user();

        $shares = Share::where("receiver", $user->username)->where("accepted", false)->get();

        return response()->json($shares, 200);
    }
    public function listSent(Request $request) {
        $user = Auth::user();
        
        $shares = Share::where("sender", $user->username)->get();
        
        return response()->json($shares, 200);
    }
}
