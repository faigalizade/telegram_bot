<?php

namespace App\Http\Controllers;

use App\Webhooks\TelegraphWebhookHandler;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function handle(Request $request, $token)
    {
        (new TelegraphWebhookHandler())
            ->handle($request, TelegraphBot::fromToken($token));
    }
}
