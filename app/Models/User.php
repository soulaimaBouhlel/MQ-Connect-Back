<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="User",
 *     title="User",
 *     description="User model",
 *     required={"id", "name", "email", "role"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Soulaima Bouhlel"),
 *     @OA\Property(property="email", type="string", format="email", example="soulaima@example.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, example="2023-01-01T12:00:00Z"),
 *     @OA\Property(property="role", type="string", enum={"publisher", "influencer"}, example="publisher"),
 *     @OA\Property(property="verified", type="boolean", example=true),
 *     @OA\Property(property="bio", type="string", example="Marketing expert and growth strategist."),
 *     @OA\Property(property="location", type="string", example="Sfax, Tunisia"),
 *     @OA\Property(property="company", type="string", nullable=true, example="MQ-Connect"),
 *     @OA\Property(property="position", type="string", nullable=true, example="Founder & CEO"),
 *     @OA\Property(property="profile_photo", type="string", nullable=true, example="https://example.com/images/avatar.jpg"),
 *     @OA\Property(property="cover_photo", type="string", nullable=true, example="https://example.com/images/cover.jpg"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T12:00:00Z")
 * )
 */

class User extends Authenticatable
{

    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'verified',
        'bio',
        'location',
        'profile_photo',
        'cover_photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    // Relationships

    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class);
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(SocialLink::class, 'influencer_id');
    }
    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }
    // Ratings given by this user (Reviewer)
    public function ratingsGiven()
    {
        return $this->hasMany(Rating::class, 'reviewer_id');
    }

    // Ratings received by this user (Reviewee)
    public function ratingsReceived()
    {
        return $this->hasMany(Rating::class, 'reviewee_id');
    }

    // Boosts by this user
    public function boosts()
    {
        return $this->hasMany(Boost::class, 'user_id');
    }

    // Followers (users who follow this user)
    public function followers()
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'followed_id',
            'follower_id'
        );
    }

    // Followings (users this user follows)
    public function followings()
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'follower_id',
            'followed_id'
        );
    }
}
