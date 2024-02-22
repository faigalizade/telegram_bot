<?php

namespace App\Webhooks;

use App\Facades\PlatformFacade;
use App\Models\Playlist;
use App\Models\User as UserModel;
use App\Services\OpenAiService;
use App\Services\PlatformServices\Spotify;
use App\Services\TelegraphChatService;
use App\Services\UserService;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Stringable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

//464866772
class TelegraphWebhookHandler extends WebhookHandler
{
    protected function handleChatMessage(Stringable $text): void
    {
        $status = $this->getStatus();
        if ($status === UserModel::STATUS_CHAT) {
            $this->handleChatGpt($text);
            return;
        }

        if ($status === UserModel::STATUS_NEXT_STEP) {
            $this->handlePlaylistName($text);
            return;
        }

        $this->start(true);
    }

    protected function handleUnknownCommand(Stringable $text): void
    {
        $this->start(true);
    }


    public function start($deleteKeyboard = false)
    {
        $this->setStatus(UserModel::STATUS_NOTHING);
        $user = UserService::createOrGetUser($this->chat->chat_id, $this->message->from());
        OpenAiService::remove($this->chat->chat_id);
        $platform = $user->getPlatform('Spotify');
        if (!$platform) {
            $this->setStatus(UserModel::STATUS_AUTHENTICATION);
            $this->send($this->chat->message(config('bot.start_message_with_auth'))
                ->keyboard(Keyboard::make()->row([
//                    Button::make('Authenticate')->action('authenticate'),
                    Button::make('Authenticate')->url(env('APP_URL') . "/authorize/spotify?chat-id={$user->chat_id}&bot={$this->bot->id}"),
                ])), deleteKeyboard: $deleteKeyboard);
            return;
        }

        $this->send($this->chat->message(config('bot.start_message'))
            ->keyboard(Keyboard::make()->row([
                Button::make('Logout')->action('logout'),
                Button::make('Create')->action('create'),
            ])), deleteKeyboard: $deleteKeyboard);
    }

    public function create()
    {
        $user = UserService::getUserByChatId($this->chat->chat_id);
//        if ($user->balance == 0) {
//            $this->setStatus(UserModel::STATUS_NOTHING);
//            $this->send($this->chat->message("Ooops!\nYou don't have enough balance to create a playlist. Please try later!"), true);
//        }
        OpenAiService::remove($this->chat->chat_id);
        $this->setStatus(UserModel::STATUS_CHAT);
        $this->send($this->chat->message(config('bot.create_message'))
            ->keyboard(Keyboard::make()->row([
                Button::make('Cancel')->action('cancel'),
            ])), true);
    }

    public function logout()
    {
        $user = UserService::getUserByChatId($this->chat->chat_id);
        $platform = $user->getPlatform('Spotify');
        $platform->delete();
        $this->setStatus(UserModel::STATUS_NOTHING);
        OpenAiService::remove($this->chat->chat_id);
        $this->send($this->chat->message("Logged out successfully!")
            ->keyboard(Keyboard::make()->row([
                Button::make('Authenticate')->url(env('APP_URL') . "/authorize/spotify?chat-id={$user->chat_id}&bot={$this->bot->id}"),
            ])), true);
    }

//    public function authenticate()
//    {
//        $user = UserService::getUserByChatId($this->chat->chat_id);
//        $platform = $user->getPlatform('Spotify');
//        if (!$platform) {
//            $this->setStatus(UserModel::STATUS_AUTHENTICATION);
//            $this->send($this->chat->message('To continue, please authenticate')
//                ->keyboard(Keyboard::make()
//                    ->row([
//                        Button::make('Spotify')->url(env('APP_URL') . "/authorize/spotify?chat-id={$user->chat_id}&bot={$this->bot->id}"),
//                    ])));
//        } else {
//
//        }
//    }


    private function getStatus()
    {
        return Cache::get("chat_{$this->chat->chat_id}_mode");
    }


    private function setStatus(string $status, $expire = 240): void
    {
        Cache::put("chat_{$this->chat->chat_id}_mode", $status, $expire);
    }


    private function handleChatGpt($message)
    {
        TelegraphChatService::clearLastMessageKeyboard($this->chat->chat_id);
        $this->send($this->chat
            ->message('Loading...'));
        $user = UserService::getUserByChatId($this->chat->chat_id);
        $response = OpenAiService::chat($this->chat->chat_id, $message->toString(), $user->id);
        if ($response) {
            $this->send($this->chat
                ->message($response)
                ->keyboard(Keyboard::make()->row([
                    Button::make('Cancel')->action('cancel'),
                    Button::make('Next')->action('next_step'),
                ])), replaceMessage: true);
        } else {
            $this->send($this->chat
                ->message('Error'));
        }
    }

    private function clearLastMessageKeyboard()
    {
        Telegraph::deleteKeyboard($this->getLastMessage())->send();
    }


    private function handlePlaylistName($message)
    {
        Cache::forever("chat_{$this->chat->chat_id}_playlist_name", $message);
        $this->send($this->chat
            ->message("Playlist: $message \nTracks:\n" . OpenAiService::getTracksPreview($this->chat->chat_id))
            ->keyboard(Keyboard::make()->row([
                Button::make('Cancel')->action('cancel'),
                Button::make('Save')->action('save_playlist'),
            ])), deleteKeyboard: true);

    }

    public function save_playlist()
    {
        $this->setStatus(UserModel::STATUS_NOTHING);
        $this->send($this->chat->message('Creating playlist...'), deleteKeyboard: true);
        TelegraphChatService::clearLastMessageKeyboard($this->chat->chat_id);

        $responseTracks = OpenAiService::getArrayOfTracks($this->chat->chat_id);
        $user = UserService::getUserByChatId($this->chat->chat_id);
        $platform = $user->getPlatform('Spotify');

        $data = PlatformFacade::make(Spotify::class)
            ->createPlaylist(Cache::get("chat_{$this->chat->chat_id}_playlist_name"), $responseTracks, $platform->access_token);
        $playlistUrl = $data['playlist']['external_urls']['spotify'];


        Playlist::query()->create([
            'user_id' => $user->id,
            'platform_id' => $platform->platform_id,
            'playlist_id' => $data['playlist']['id'],
            'token' => OpenAiService::getLastChatTokens($this->chat->chat_id),
        ]);

        OpenAiService::remove($this->chat->chat_id);
        $this->send($this->chat->message("Playlist created successfully")->keyboard(Keyboard::make()->row([
            Button::make('Create new')->action('create'),
            Button::make('Logout')->action('logout'),
            Button::make('Open')->url($playlistUrl)
        ])), deleteKeyboard: true, replaceMessage: true);
    }

    public function cancel()
    {
        $this->setStatus(UserModel::STATUS_NOTHING);
//        Telegraph::deleteMessage($this->getLastMessage())->send();
        OpenAiService::remove($this->chat->chat_id);
        $this->send($this->chat
            ->message("Process cancelled\nBut you can try again by clicking Create button")
            ->keyboard(Keyboard::make()->row([
                Button::make('Logout')->action('logout'),
                Button::make('Create')->action('create'),
            ])), true, true);
    }


    public function next_step(): void
    {
        $this->setStatus(UserModel::STATUS_NEXT_STEP);
        TelegraphChatService::clearLastMessageKeyboard($this->chat->chat_id);
        $this->send($this->chat->message(config('bot.next_step_message'))
            ->keyboard(Keyboard::make()->row([
                Button::make('Cancel')->action('cancel'),
            ])));
    }

    private function setLastMessage($response)
    {
        TelegraphChatService::setLastMessage($this->chat->chat_id, $response->telegraphMessageId());
    }

    private function getLastMessage()
    {
        return TelegraphChatService::getLastMessage($this->chat->chat_id);
    }


    private function send(\DefStudio\Telegraph\Telegraph $telegraph, $deleteKeyboard = false, $replaceMessage = false)
    {
        TelegraphChatService::send($telegraph, $this->chat->chat_id, $deleteKeyboard, $replaceMessage);
    }


    protected function onFailure(Throwable $throwable): void
    {
        if ($throwable instanceof NotFoundHttpException) {
            throw $throwable;
        }

        report($throwable);
        $this->reply('Ooops! Something went wrong');
    }
}




//        $this->chat->message("Received: $text")->keyboard(Keyboard::make()->buttons([
//            Button::make('Delete')->action('delete')->param('id', '42'),
//            Button::make('open')->url('https://test.it'),
//            Button::make('Web App')->webApp('https://newave.az'),
//            Button::make('Login Url')->loginUrl('https://loginUrl.test.it'),
//        ]))->send();
