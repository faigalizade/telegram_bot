<?php

namespace App\Services;

use DefStudio\Telegraph\Models\TelegraphChat;
use DefStudio\Telegraph\Telegraph;
use Illuminate\Support\Facades\Cache;

class TelegraphChatService
{
    public static function setLastMessage($chatId, $messageId)
    {
        $key = "chat_{$chatId}_messages";
        $messages = Cache::get($key) ?? [];


        array_unshift($messages, $messageId);
        Cache::forever($key, $messages);
    }

    public static function getLastMessage($chatId)
    {
        $messages = self::getMessages($chatId);
        return $messages ? $messages[0] : 0;
    }

    private static function getMessages($chatId)
    {
        $key = "chat_{$chatId}_messages";
        return Cache::get($key) ?? [];
    }


    public static function send(Telegraph $telegraph, $chatId, $deleteKeyboard = false, $replaceMessage = false)
    {
        if ($deleteKeyboard) {
            $chat = TelegraphChat::query()->where('chat_id', $chatId)->first();
            $lastMessage = self::getLastMessage($chatId);
            if ($lastMessage) {
                $chat->deleteKeyboard(self::getLastMessage($chatId))->send();
            }
        }


        if ($replaceMessage) {
            $telegraph = $telegraph->edit(self::getLastMessage($chatId));
        }


        self::setLastMessage($chatId, $telegraph->send()->telegraphMessageId());
    }

    public static function clearLastMessageKeyboard($chatId): void
    {
        $chat = TelegraphChat::query()->where('chat_id', $chatId)->first();
        $lastMessage = self::getLastMessage($chatId);
        if ($lastMessage) {
            $chat->deleteKeyboard(self::getLastMessage($chatId))->send();
        }
    }
}
