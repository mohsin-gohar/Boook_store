<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A six-digit code issued by the "forgot password" flow.
 */
class PasswordResetCode extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
