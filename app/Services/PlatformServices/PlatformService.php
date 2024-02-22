<?php

namespace App\Services\PlatformServices;

use Illuminate\Http\Request;

interface PlatformService
{
    public function getAuthUrl(Request $request): string|null;

    public function getAccessToken(Request $request): array|null|string;

    public function fail(Request $request): array|null|string;

    public function refreshToken(string $refreshToken): array|null|string;

    public function createPlaylist(string $playlistName, array $tracks, string $accessToken): array|null|string;
}
