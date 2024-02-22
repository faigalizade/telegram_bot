<?php

use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\TelegramController;
use App\Telegram\Commands\AuthenticateCommand;
use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {


//    $chat = TelegraphChat::first();
////    $chat->sen
////    dd($chat);
//
//    /*$openAi = new OpenAi(env('OPENAI_SECRET_KEY'));
//    $chat = $openAi->chat([
//        'model' => 'gpt-4',
//        'messages' => [
//            [
//                "role" => "user",
//                "content" => "создай плей-лист для спортзала из 5 легендарных рок песен. Перечисли в 1 линию с запятой"
//            ]
//        ],
//        'temperature' => 1.0,
//        'max_tokens' => 4000,
//        'frequency_penalty' => 0,
//        'presence_penalty' => 0,
//    ]);
//
//    $response = json_decode($chat);
//    $responseTracks = explode(",", $response->choices[0]->message->content);*/
//    $trackUris = [];
//    foreach ($responseTracks as $track) {
//        $spotifyTrack = Spotify::searchTracks($track)->get()['tracks']['items'][0];
//        $trackUris[] = [
//            'id' => $spotifyTrack['id'],
//            'uri' => $spotifyTrack['uri']
//        ];
//    }
//    dd(join(',', array_column($trackUris, 'uri')));
////    dd();
//    dd(TelegraphBot::fromId(1)->info()['username']);
//    Telegraph::chat('464866772')->message('hello world')
//        ->keyboard(Keyboard::make()->buttons([
//            Button::make('Delete')->action('delete')->param('id', '42'),
//            Button::make('open')->url('https://test.it'),
//            Button::make('Web App')->webApp('https://newave.az'),
////            Button::make('Login Url')->loginUrl('https://loginUrl.test.it'),
//        ]))->send();
//    TelegraphFacade::chat(464866772)
//    Telegraph::;

//    dd(Telegram::addCommand(AuthenticateCommand::class));
});

Route::post('/webhook/telegram', [TelegramController::class, 'handle']);
Route::post('/telegraph/{token}/webhook', [TelegramController::class, 'handle'])->name('telegraph.webhook');
Route::get('/authorize/spotify', [SpotifyController::class, 'handle']);
Route::get('/callback/spotify', [SpotifyController::class, 'callback']);
