<?php

namespace App\Services;

use App\Models\UserChat;
use Illuminate\Support\Facades\Cache;
use Orhanerday\OpenAi\OpenAi;

class OpenAiService
{
    public static function chat($chat_id, $prompt, $userId = null)
    {
        $openAi = new OpenAi(env('OPENAI_SECRET_KEY'));
        $messages = Cache::get("messages_of_$chat_id") ?? [
            [
                'role' => 'system',
                'content' => config("bot.chat_gpt_system_message")
            ]
        ];

        $messages[] = ['role' => 'user', 'content' => $prompt];


        $chat = $openAi->chat([
            'model' => 'gpt-4',
            'messages' => $messages,
            'temperature' => 1,
            'max_tokens' => 4000,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);


        $response = json_decode($chat);
        $message = @$response?->choices[0]?->message?->content ?? null;


        if ($userId) {
            $lastChatTokensKey = "last_chat_tokens_of_$chat_id";
            $totalTokens = Cache::get($lastChatTokensKey) ?? 0;
            UserChat::query()->create([
                'user_id' => $userId,
                'message' => $prompt,
                'response' => $message ?? 'Error',
                'prompt_tokens' => @$response?->usage?->prompt_tokens ?? 0,
                'completion_tokens' => @$response?->usage?->completion_tokens ?? 0,
                'total_tokens' => @$response?->usage?->total_tokens ?? 0,
            ]);

            $totalTokens += (@$response?->usage?->total_tokens ?? 0);
            Cache::forever($lastChatTokensKey, $totalTokens);
        }

        if (!$message) {
            return $message;
        }

        $messages[] = [
            'role' => 'assistant',
            'content' => $message,
        ];

        Cache::forever("messages_of_$chat_id", $messages);

        return $message;
    }

    public static function getArrayOfTracks($chat_id): array
    {
        $data = self::chat($chat_id, 'Прочислай это все в 1 строку через запятую');
        return explode(',', $data);
    }

    public static function getTracksPreview($chat_id)
    {
        $messages = Cache::get("messages_of_$chat_id");
        return end($messages)['content'];
    }

    public static function remove($chat_id): void
    {
        Cache::forget("messages_of_$chat_id");
        Cache::forget("last_chat_tokens_of_$chat_id");
    }


    public static function getLastChatTokens($chat_id)
    {
        return Cache::get("last_chat_tokens_of_$chat_id") ?? 0;
    }
}
