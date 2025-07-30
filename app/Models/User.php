<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
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
