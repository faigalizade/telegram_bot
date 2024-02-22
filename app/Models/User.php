<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    const STATUS_CHAT = 'chat';
    const STATUS_NEXT_STEP = 'next_step';
    const STATUS_NOTHING = 'nothing';
    const STATUS_CREATE_PLAYLIST = 'create_playlist';
    const STATUS_AUTHENTICATION = 'authentication';


    protected bool $is_new = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function platforms()
    {
        return $this->hasOne(UserPlatform::class);
    }

    public function getPlatform($platformName): UserPlatform|Model|bool
    {
        $platform = Platform::query()->where('name', $platformName)->first();
        $userPlatform = UserPlatform::query()
            ->where('platform_id', $platform->id)
            ->where('user_id', $this->id)
            ->first();

        if (!$userPlatform) {
            return false;
        }

        return UserPlatform::query()
            ->where('platform_id', $platform->id)
            ->where('user_id', $this->id)
            ->first();
    }
}
