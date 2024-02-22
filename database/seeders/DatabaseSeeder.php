<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Platform;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $spotify = new Platform();
        $spotify->id = 1;
        $spotify->name = 'Spotify';
        $spotify->display_name = 'Spotify';
        $spotify->active = true;
        $spotify->save();
    }
}
