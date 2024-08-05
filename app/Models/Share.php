<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    use HasFactory;

    protected $table = 'shares';

    protected $fillable = [
        'sender', 'receiver', 'video', 'accepted'
    ];
}
