<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Telegram\Bot\Laravel\Facades\Telegram;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('test-spotify', function () {
    $scopes = 'playlist-modify-private playlist-modify-public';
    $redirectUri = "https://gpttelegram.newave.az/callback/spotify?user=1";
    $clientId = env('SPOTIFY_CLIENT_ID');
    $authUrl = "https://accounts.spotify.com/authorize?client_id={$clientId}&response_type=code&redirect_uri={$redirectUri}&scope={$scopes}";

    dd($authUrl);
//    return redirect()->away($authUrl);
//    dd(Spotify::playl('Closed on Sunday')->get());
});


Artisan::command('test-telegram', function () {
//   dd(Telegram::getMe());
    $response = Telegram::setWebhook(['url' => env('TELEGRAM_WEBHOOK_URL')]);
});
