<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

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
    protected $casts = [
        'price' => 'decimal:2',
        'budget' => 'decimal:2',
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
        return $this->belongsToMany(Tag::class, 'offer_tag', 'offer_id', 'tag_id');
    }
}
