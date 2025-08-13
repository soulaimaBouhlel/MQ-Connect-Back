<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * @OA\Schema(
 *     schema="Offer",
 *     type="object",
 *     title="Offer",
 *     required={"id", "title", "status"},
 *    @OA\Property(property="user_id", type="integer", example=1),
 *              @OA\Property(property="title", type="string", example="Instagram Campaign"),
 *              @OA\Property(property="description", type="string", example="Promote our new product"),
 *              @OA\Property(property="goals", type="string", example="Increase brand awareness"),
 *              @OA\Property(property="budget", type="number", format="float", example=1500.00),
 *              @OA\Property(property="criteria", type="string", example="At least 10k followers"),
 *              @OA\Property(property="niche", type="string", example="Fashion"),
 *              @OA\Property(property="status", type="string", example="open")
 * )
 */

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
        'criteria' => 'array',

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
