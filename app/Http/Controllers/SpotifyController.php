<?php

namespace App\Http\Controllers;

use App\Facades\PlatformFacade;
use App\Services\PlatformServices\Spotify;
use Illuminate\Http\Request;


class SpotifyController extends Controller
{
    public function handle(Request $request)
    {
        return redirect(PlatformFacade::make(Spotify::class)->getAuthUrl($request));
    }

    public function callback(Request $request)
    {
        if (!$request->get('code')) {
            return redirect(PlatformFacade::make(Spotify::class)->fail($request));
        }

        return redirect(PlatformFacade::make(Spotify::class)->getAccessToken($request));
    }
}
