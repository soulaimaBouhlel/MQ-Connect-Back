<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    use HasFactory;

    protected $fillable = [
        'follower_id',
        'following_id',
    ];

    // The follower user
    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    // The followed user
    public function followed()
    {
        return $this->belongsTo(User::class, 'followed_id');
    }
}
