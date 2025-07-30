<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'goals',
        'budget',
        'criteria',
        'niche',
        'status',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'offer_tag');
    }
}
