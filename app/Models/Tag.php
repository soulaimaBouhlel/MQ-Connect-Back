<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    // Many-to-many relationship with Offer through offer_tag pivot table
    public function offers()
    {
        return $this->belongsToMany(Offer::class, 'offer_tag', 'tag_id', 'offer_id');
    }
}
