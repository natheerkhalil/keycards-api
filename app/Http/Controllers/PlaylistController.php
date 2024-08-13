<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Relationship;

use App\Models\Video;

use Illuminate\Support\Facades\Auth;

class PlaylistController extends Controller
{
    // ADD VIDEO TO PLAYLIST
    public function add(Request $request)
    {
        // CHECK THAT VIDEO URL AND PLAYLIST ID HAVE BEEN PROVIDED
        $request->validate([
            "plid" => "required|integer|exists:playlists,id",
            "url" => "required|exists:videos,url"
        ]);

        $user = Auth::user();

        // GET VIDEO AND PLAYLIST THAT BELONG TO AUTHENTICATED USER
        $video = Video::where("username", $user->username)->where("url", $request->url)->firstOrFail();
        $playlist = Playlist::where("id", $request->plid)->where("username", $user->username)->firstOrFail();

        // CHECK THAT VIDEO IS NOT ALREADY IN THE PLAYLIST
        $relationship = Relationship::where("video", $video->id)->where("playlist", $playlist->id)->first();
        if ($relationship) {
            return response()->json([
                "message" => "Video already exists in this playlist."
            ], 409);
        }

        // ADD VIDEO TO PLAYLIST
        Relationship::create([
            "video" => $video->id,
            "playlist" => $playlist->id,
            "username" => $user->username
        ]);

        return response()->json([
            "message" => "Video added to playlist successfully"
        ], 201);
    }

    // REMOVE VIDEO FROM PLAYLIST
    public function remove(Request $request)
    {
        // CHECK THAT VIDEO URL AND PLAYLIST ID HAVE BEEN PROVIDED
        $request->validate([
            "plid" => "required|integer|exists:playlists,id",
            "url" => "required|exists:videos,url"
        ]);

        $user = Auth::user();

        // GET VIDEO AND PLAYLIST THAT BELONG TO AUTHENTICATED USER
        $video = Video::where("username", $user->username)->where("url", $request->url)->firstOrFail();
        $playlist = Playlist::where("id", $request->plid)->where("username", $user->username)->firstOrFail();

        // REMOVE VIDEO FROM PLAYLIST
        $relationship = Relationship::where("video", $video->id)->where("playlist", $playlist->id)->firstOrFail();
        $relationship->delete();

        return response()->json([
            "message" => "Video added to playlist successfully"
        ], 201);
    }

    // CREATE PLAYLIST
    public function create(Request $request)
    {
        // CHECK THAT PLAYLIST NAME HAS BEEN PROVIDED
        $request->validate([
            "name" => "required|string|max:50"
        ]);

        $user = Auth::user();

        // CHECK IF USER HAS REACHED PLAYLIST LIMIT
        $membership = $user->membership;

        if ($membership == "0" && Playlist::where("username", $user->username)->count() >= 9) {
            return response()->json(["error" => "You have reached your playlist limit"], 403);
        }

        // CREATE PLAYLIST AND ASSIGN IT TO THE AUTHENTICATED USER
        $playlist = Playlist::create([
            "name" => $request->name,
            "username" => $user->username,
        ]);

        return response()->json([
            "data" => $playlist
        ], 201);
    }

    // DELETE PLAYLIST
    public function delete(Request $request)
    {
        // CHECK THAT PLAYLIST ID HAS BEEN PROVIDED
        $request->validate([
            "id" => "required|integer|exists:playlists,id"
        ]);

        $user = Auth::user();

        // GET PLAYLIST WHERE ID BELONGS TO THE AUTHENTICATED USER
        $playlist = Playlist::where("id", $request->id)->where("username", $user->username)->firstOrFail();

        // DELETE ALL RELATIONSHIPS WITH THIS PLAYLIST
        $relationships = Relationship::where("username", $user->username)->where("playlist", $playlist->id)->get();
        foreach ($relationships as $r) {
            $r->delete();
        }

        // DELETE PLAYLIST
        $playlist->delete();

        return response()->json([
            "data" => "Playlist deleted successfully"
        ], 200);
    }

    // GET PLAYLIST VIDEOS 
    public function videos(Request $request)
    {
        // VALIDATE THAT PLAYLIST ID HAS BEEN PROVIDED & EXISTS
        $request->validate([
            "id" => "required|integer|exists:playlists,id"
        ]);

        $user = Auth::user();

        // GET PLAYLIST WHERE ID BELONGS TO THE AUTHENTICATED USER
        $playlist = Playlist::where("id", $request->id)->where("username", $user->username)->firstOrFail();

        // RETURN VIDEOS IN THE PLAYLIST
        $videos = $playlist->videos;

        return response()->json([
            "data" => $videos
        ]);
    }

    // UPDATE PLAYLIST
    public function update(Request $request)
    {
        // VALIDATE THAT PLAYLIST ID AND NEW PLAYLIST NAME HAVE BEEN PROVIDED
        $request->validate([
            "id" => "required|integer|exists:playlists,id",
            "name" => "required|string|max:35"
        ]);

        $user = Auth::user();

        // GET PLAYLIST WHERE ID BELONGS TO THE AUTHENTICATED USER
        $playlist = Playlist::where("id", $request->id)->where("username", $user->username)->firstOrFail();

        // UPDATE PLAYLIST NAME
        $playlist->update([
            "name" => $request->name
        ]);

        return response()->json([
            "data" => "Playlist updated successfully"
        ], 200);
    }

    // GET ALL PLAYLISTS
    public function list()
    {
        $user = Auth::user();

        $playlists = Playlist::where("username", $user->username)->get();

        // SET THUMBNAIL FOR EACH PLAYLIST
        foreach ($playlists as $pl) {
            $video = $pl->videos->first();

            $pl->thumbnail = $video ? $video->thumbnail : "https://i.ytimg.com/vi/VIDEO_ID/hqdefault.jpg";
        }

        return response()->json([
            "data" => $playlists
        ], 200);
    }
}
