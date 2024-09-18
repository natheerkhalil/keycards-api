<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

class FolderController extends Controller
{
    private function getThemes() {
        return [
            "aura",
            "dune",
            "ciel",
            "topiary",
            "navy",
            "alpine",
            "eventide",
            "mythical",
            "shroud",
            "lite"
        ];
    }

    // Permission check for folder
    private function isDescendant($folderId, $parentId, $username)
    {
        $parent = Folder::where("creator", $username)->where("id", $parentId)->first();

        while ($parent) {
            if ($parent->parent == $folderId) {
                return true;
            }
            $parent = Folder::where("creator", $username)->where("id", $parent->parent)->first();
        }

        return false;
    }


    // C . R . U . D //

    // CREATE
    public function create(Request $request)
    {
        $themes = implode(",", $this->getThemes());
        try {
            $request->validate([
                "name" => "required|string|max:255|min:1",
                "theme" => "required|string|in:$themes",
                "parent" => "nullable|exists:folders,id",
            ]);

            $user = Auth::user();

            if ($request->parent) {
                $parent = Folder::where("creator", $user->username)->where("id", $request->parent)->first();

                if (!$parent) {
                    $request->parent = null;
                }
            }

            $folder = new Folder();
            $folder->name = $request->name;
            $folder->creator = $user->username;
            $folder->theme = $request->theme;
            $folder->parent = $request->parent;
            $folder->save();

            return response()->json(["id" => $folder->id]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // READ
    public function read(Request $request)
    {
        try {
            $request->validate([
                "folder" => "required|string|exists:folders,id",
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();

            return response()->json(["data" => $folder]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // UPDATE   
    public function update(Request $request)
    {
        try {
            $request->validate([
                "folder" => "required|string|exists:folders,id",
                "name" => "nullable|string|max:255|min:1",
                "parent" => "nullable|string|exists:folders,id",
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();

            if ($request->name) {
                $folder->name = $request->name;
            }

            if ($request->parent) {
                $parent = Folder::where("creator", $user->username)->where("id", $request->parent)->first();

                if (!$parent) {
                    $request->parent = null;
                }

                $folder->parent = $request->parent;
            }

            $folder->save();

            return response()->json(["data" => $folder]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // DELETE
    public function delete(Request $request)
    {
        try {
            $request->validate([
                "folder" => "required|string|exists:folders,id",
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();

            $folder->delete();

            return response()->json(["data" => "Folder deleted successfully"]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // END C . R . U . D //



    // MOVE //
    public function move(Request $request)
    {
        try {
            $request->validate([
                "folder" => "required|string|exists:folders,id",
                "parent" => "required|string|exists:folders,id",
            ]);

            $user = Auth::user();

            $folder = Folder::where("creator", $user->username)->where("id", $request->folder)->firstOrFail();
            $parent = Folder::where("creator", $user->username)->where("id", $request->parent)->firstOrFail();

            if ($folder->parent == $parent->id) {
                return response()->json(["error" => "Folder already belongs to the specified parent folder"], 400);
            }

            // Check if the target parent is a descendant of the folder being moved
            if ($this->isDescendant($folder->id, $parent->id, $user->username)) {
                return response()->json(["error" => "Cannot move folder to one of its descendants"], 400);
            }

            $folder->parent = $parent->id;
            $folder->save();

            return response()->json(["data" => "Folder moved successfully"]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // CARDS //
    public function cards(Request $request)
    {
        try {
            $request->validate([
                "folder" => "required|string|exists:folders,id",
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    // LIST //
    public function list()
    {
        $user = Auth::user();

        $folders = Folder::where("creator", $user->username)->where("parent", null)->get();

        return response()->json(["data" => $folders]);
    }

    // CHILDREN //
    public function children(Request $request)
    {
        try {
            $request->validate([
                "folder" => "required|string|exists:folders,id",
            ]);

            $folder = Folder::where("creator", Auth::user()->username)->where("id", $request->folder)->firstOrFail();

            $children = $folder->children;

            return response()->json(["data" => $children]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }
}
