<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use App\Models\Card;
use App\Models\Folder;

class DataController extends Controller
{
    public function all() {
        $user = Auth::user();
        
        $folders = Folder::where("creator", $user->username)->get();
        $cards = Card::where("creator", $user->username)->get();
        
        return response()->json(["folders" => $folders, "cards" => $cards], 200);
    }
}
