<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        $this->call([
            CategorySeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $users = User::factory(20)->create();
        $users->push($admin);

        /*
        |--------------------------------------------------------------------------
        | Posts
        |--------------------------------------------------------------------------
        */

        $posts = Post::factory(50)
            ->recycle($users)
            ->create();

        /*
        |--------------------------------------------------------------------------
        | Post Images
        |--------------------------------------------------------------------------
        */

        foreach ($posts as $post) {
            PostImage::factory(rand(2, 5))->create([
                'post_id' => $post->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Comments
        |--------------------------------------------------------------------------
        */

        foreach ($posts as $post) {
            Comment::factory(rand(2, 8))->create([
                'post_id' => $post->id,
                'user_id' => $users->random()->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Likes
        |--------------------------------------------------------------------------
        */

        foreach ($posts as $post) {
            $randomUsers = $users->random(
                min(rand(2, 10), $users->count())
            );

            foreach ($randomUsers as $user) {
                Like::firstOrCreate([
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                ]);
            }
        }
    }
}
