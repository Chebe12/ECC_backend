<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Admin extends Authenticatable implements JWTSubject
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified',
        'email_verified_at',
        'password',
        'country_code',
        'phone',
        'gender',
        'address',
        'profile_picture',
        'cover_image',
        'date_of_birth',
        'bio',
        'is_active',
        'is_verified',
        'is_banned',
        'is_deleted',
        'session_id',
        'ip_address',
        'user_agent',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'phone_verified_at',
        'phone_verified',
        'is_verified',
        'is_banned',
        'is_deleted',
        'is_suspended',
        'session_id',
        'ip_address',
        'user_agent',
        'role',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function auctions()
    {
        return $this->hasMany(Auction::class);
    }


    protected $appends = ['profile_picture_url', 'cover_image_url'];
    public function getProfilePictureUrlAttribute()
    {
        return $this->profile_picture ? asset('storage/' . $this->profile_picture) : null;
    }
    public function getCoverImageUrlAttribute()
    {
        return $this->cover_image ? asset('storage/' . $this->cover_image) : null;
    }


    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
}
