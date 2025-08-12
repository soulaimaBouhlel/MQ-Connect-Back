<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @OA\Schema(
 *     schema="MediaItem",
 *     type="object",
 *     title="Media Item",
 *     required={"title", "url", "type", "portfolio_item_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="UI Design Sample"),
 *     @OA\Property(property="url", type="string", format="url", example="https://example.com/media.png"),
 *     @OA\Property(property="type", type="string", example="image"),
 *     @OA\Property(property="portfolio_item_id", type="integer", example=5),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-08T12:34:56Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-08T12:34:56Z")
 * )
 */
class MediaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_item_id',
        'file_path',
    ];

    public function portfolioItem(): BelongsTo
    {
        return $this->belongsTo(PortfolioItem::class);
    }
}
