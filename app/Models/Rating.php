<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'reviewer_id',
        'reviewee_id',
        'rating',
        'comment',
    ];

    // Reviewer user relationship
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    // Reviewee user relationship
    public function reviewee()
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
