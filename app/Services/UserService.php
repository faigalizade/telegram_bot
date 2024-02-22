<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserService
{
    public static function createOrGetUser($chaId, $from = null): User
    {
        $user = User::query()->where('chat_id', $chaId)->first();
        if (!$user) {
            $user = new User();
            $user->chat_id = $chaId;
//            $user->is_new = true;
        }


        if ($from) {
            $user->first_name = $from->firstName();
            $user->last_name = $from->lastName();
            $user->username = $from->username();
            $user->is_bot = $from->isBot();
            $user->language_code = $from->languageCode();
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }

    public static function getUserByChatId($chatId): User|Model
    {
        return self::createOrGetUser($chatId);
//        return Cache::rememberForever("user_$chatId", function () use ($chatId) {
//            return User::query()->where('chat_id', $chatId)->first();
//        });
    }
}
