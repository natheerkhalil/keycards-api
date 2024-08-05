<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Video;
use App\Models\Share;
use App\Models\User;

use Illuminate\Support\Facades\Auth;

class ShareController extends Controller
{
    // CREATE SHARE
    public function create(Request $request)
    {
        // VALIDATE THAT USERNAME OF USER TO BE SHARED WITH AND URL OF VIDEO HAVE BEEN PROVIDED
        $request->validate([
            "username" => "required|string|exists:users,username",
            "url" => "required|exists:videos,url"
        ]);

        $user = Auth::user();

        try {

            // GET VIDEO VIA ID FROM AUTHENTICATED USER
            $video = Video::where("url", $request->url)->where("username", $user->username)->firstOrFail();

            // GET USER TO BE SHARED WITH VIA USERNAME
            $receiver = User::where("username", $request->username)->firstOrFail();

            // CHECK IF THIS VIDEO HAS ALREADY BEEN SHARED WITH THIS USER
            $existing_share = Share::where("sender", $user->username)->where("receiver", $request->username)->where("video", $video->id)->first();

        } catch (\Exception $e) {
            return response()->json(["data" => $e->getMessage()], 422);
        }

        if ($existing_share) {
            return response()->json(["data" => "You have already shared this video with this user."], 400);
        }

        // CHECK IF THIS USER ALREADY HAS THIS VIDEO IN THEIR LIBRARY
        $existing_video = Video::where("url", $request->url)->where("username", $receiver->username)->first();

        if ($existing_video) {
            return response()->json(["data" => "This user already has this video in their library."], 400);
        }

        // CHECK IF USER IS SHARING THIS VIDEO WITH THEMSELVES
        if ($user->username == $request->receiver) {
            return response()->json(["data" => "You cannot share a video with yourself"], 400);
        }

        // CREATE NEW SHARE RECORD AND ASSOCIATE IT WITH THE VIDEO AND USERS
        Share::create([
            "sender" => $user->username,
            "receiver" => $request->username,
            "video" => $video->id
        ]);

        return response()->json(["data" => "Video shared successfully"], 200);
    }

    // ACCEPT OR REJECT RECEIVED SHARE
    public function respond(Request $request)
    {
        // VALIDATE THAT SHARE ID AND RESPONSE (ACCEPTED OR REJECTED) HAVE BEEN PROVIDED
        $request->validate([
            "id" => "required|integer|exists:shares,id",
            "res" => "required|boolean"
        ]);

        $user = Auth::user();

        // GET SHARE RECORD VIA ID AND USERNAME
        $share = Share::where("id", $request->id)->where("receiver", $user->username)->where("accepted", false)->firstOrFail();

        // IF SHARE IS ACCEPTED
        if ($request->res) {
            // GET VIDEO DATA VIA ID
            $data = Video::where("id", $share->video)->where("username", $share->sender)->select(["title", "desc", "thumbnail", "start", "end", "skip", "lyrics", "url", "speed"])->first();

            // CHANGE VIDEO USERNAME TO RECEIVER
            $data->username = $user->username;
            // CHANGE FAV STATUS TO FALSE
            $data->fav = false;

            // CONVERT VIDEO DATA TO ARRAY
            $data = $data->toArray();

            // CREATE NEW VIDEO RECORD AND ASSOCIATE IT WITH THE RECEIVER
            Video::create($data);

            // UPDATE SHARE RECORD TO ACCEPTED
            $share->update([
                "accepted" => true
            ]);
        } else {
            // IF SHARE IS REJECTED, DELETE SHARE RECORD
            $share->delete();
        }

        return response()->json(["data" => "Share responded to successfully"], 200);
    }

    // GET ALL RECEIVED SHARES
    public function list(Request $request)
    {
        $shares = Share::where("receiver", Auth::user()->username)->where("accepted", false)->get();

        $data = [];

        foreach ($shares as $s) {
            // IF VIDEO NO LONGER EXISTS, SKIP IT
            $v = Video::where("id", $s->video)->first() ? Video::where("id", $s->video)->first() : null;

            if ($v == null) {
                return;
            }

            // SERIALIZE VIDEO SKIPS
            $v->skip = unserialize($v->skip);

            // ADD VIDEO DATA TO ARRAY
            $data[] = [
                "id" => $s->id,
                "video" => $v,
                "sender" => $s->sender,
                "accepted" => false
            ];
        }

        return response()->json(["data" => $data], 200);
    }
}