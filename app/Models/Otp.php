<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Factories\HasFactory;

class Otp extends Model
{


    protected $fillable = [
        'model_type',
        'model_id',
        'code',
        'type',
        'expires_at',
        'is_used',
    ];

    /**
     * Get the model (User or Customer) that owns the OTP.
     */
    public function model()
    {
        return $this->morphTo();
    }

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user associated with the OTP.
     */
    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    protected $table = 'otps';
}
