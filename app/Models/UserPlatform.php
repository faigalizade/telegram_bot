<?php

namespace App\Models;

use App\Facades\PlatformFacade;
use App\Services\PlatformServices\Spotify;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPlatform extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function booted()
    {
        parent::boot();
        static::retrieved(function (UserPlatform $model) {
            if ($model->expires_at <= now()) {
                $model->refreshToken();
            }
        });
    }

    public function refreshToken()
    {
        $response = PlatformFacade::make(Spotify::class)->refreshToken($this->refresh_token);
        $this->access_token = $response['access_token'];
        $this->expires_at = now()->addSeconds($response['expires_in']);
        $this->save();
    }
}
