<?php

namespace App\Services\PlatformServices;

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\TelegraphChatService;
use App\Services\UserService;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Spotify as AerniSpotify;

class Spotify implements PlatformService
{

    public function getAuthUrl(Request $request): string
    {
        $chatId = $request->get('chat-id');
        $bot = $request->get('bot');

        if (!$chatId || !$bot) {
            return 'tg://resolve?domain=' . TelegraphBot::query()->first()->info()['username'];
        }

        Cookie::queue('chat-id', $chatId);
        Cookie::queue('bot-id', $bot);
        $scopes = 'playlist-modify-private playlist-modify-public';
        $redirectUri = env('APP_URL') . "/callback/spotify";
        $clientId = env('SPOTIFY_CLIENT_ID');
        return "https://accounts.spotify.com/authorize?client_id={$clientId}&response_type=code&redirect_uri={$redirectUri}&scope={$scopes}";
    }

    public function getAccessToken(Request $request): array|string|null
    {
        $code = $request->get('code');
        $redirectUri = env('APP_URL') . "/callback/spotify";
        $credentials = env('SPOTIFY_CLIENT_ID') . ":" . env("SPOTIFY_CLIENT_SECRET");
        $basic = base64_encode($credentials);
        $client = new Client();
        try {
            $response = $client->post('https://accounts.spotify.com/api/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . $basic,
                    'content-type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ],
            ]);
        } catch (Exception $exception) {
            return $exception->getMessage();
        }

        $data = json_decode($response->getBody()->getContents(), true);
        $chatId = Cookie::get('chat-id');
        $user = User::where('chat_id', $chatId)->first();
        if (!$user) {
            $user = UserService::createOrGetUser($chatId);
        }
        $platform = Platform::query()->where('name', 'Spotify')->first();


        $response = $client->get('https://api.spotify.com/v1/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $data['access_token'],
            ]
        ]);
        $spotifyUser = json_decode($response->getBody()->getContents(), true);

        UserPlatform::query()->updateOrCreate([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
        ], [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'external_id' => $spotifyUser['id'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);
        $bot = TelegraphBot::fromId(Cookie::get('bot-id'));
        /**
         * @var $chat TelegraphChat
         */
        $chat = TelegraphChat::query()->where('chat_id', $chatId)->first();

        $chat->deleteKeyboard(TelegraphChatService::getLastMessage($chatId))->send();

        TelegraphChatService::send($chat->message(config('bot.auth_success'))
            ->keyboard(Keyboard::make()->row([
                Button::make('Logout')->action('logout'),
                Button::make('Create')->action('create'),
            ])), $chatId);

        return 'tg://resolve?domain=' . $bot->info()['username'];
    }


    public function createPlaylist($playlistName, $tracks, $accessToken, $userId = null): array|string|null
    {
        $trackUris = [];
        foreach ($tracks as $track) {
            $spotifyTrack = AerniSpotify::searchTracks($track)->get()['tracks']['items'][0];
            $trackUris[] = [
                'id' => $spotifyTrack['id'],
                'uri' => $spotifyTrack['uri']
            ];
        }

        $client = new Client();

        $playlistResponse = $client->post('https://api.spotify.com/v1/me/playlists', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'content-type' => 'application/json'
            ],
            'json' => [
                'name' => $playlistName,
                'public' => false,
            ]
        ]);
        $playlist = json_decode($playlistResponse->getBody()->getContents(), true);

        $client = new Client();

        $playlistResponse = $client->post('https://api.spotify.com/v1/playlists/' . $playlist['id'] . '/tracks/', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'content-type' => 'application/json'
            ],
            'json' => [
                'uris' => array_column($trackUris, 'uri')
            ]
        ]);

        return [
            'playlist' => $playlist,
            'add_items' => json_decode($playlistResponse->getBody()->getContents(), true)
        ];
    }


    public function refreshToken(string $refreshToken): array|string|null
    {
        $client = new Client();
        $credentials = env('SPOTIFY_CLIENT_ID') . ":" . env("SPOTIFY_CLIENT_SECRET");
        $basic = base64_encode($credentials);
        try {
            $response = $client->post('https://accounts.spotify.com/api/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . $basic,
                    'content-type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $exception) {
            return null;
        }
    }


    public function fail(Request $request): array|string|null
    {
        $chatId = Cookie::get('chat-id');
        $chat = TelegraphChat::query()->where('chat_id', $chatId)->first();
        $chat->deleteKeyboard(TelegraphChatService::getLastMessage($chatId))->send();
        $bot = TelegraphBot::fromId(Cookie::get('bot-id'));

        TelegraphChatService::send($chat->message(config('bot.auth_fail'))->keyboard(Keyboard::make()->row([
            Button::make('Authenticate')->url(env('APP_URL') . "/authorize/spotify?chat-id={$chatId}&bot={$bot->id}"),
        ])), $chatId);

        $bot = TelegraphBot::fromId(Cookie::get('bot-id'));

        return 'tg://resolve?domain=' . $bot->info()['username'];
    }
}
