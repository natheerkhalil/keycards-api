<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Video;

class Playlist extends Model
{
    use HasFactory;

    protected $table = 'playlists';

    protected $fillable = [
        'name', 'username', 'desc'
    ];

    public function videos() {
        return $this->belongsToMany(Video::class, 'relationships', 'playlist', 'video');
    }
}
