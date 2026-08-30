<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PostController extends Controller
{
    /**
     * Display user's posts.
     */
    public function index()
    {
        $posts = Post::with([
                'user',
                'category',
                'images',
            ])
            ->where('status', 'published')
            ->latest('published_at')
            ->paginate(10);

        return view('posts.index', compact('posts'));
    }


    /**
     * Show create post form.
     */
    public function create()
    {
        $categories = Category::where('status', true)
            ->orderBy('name')
            ->get();

        return view('posts.create', compact('categories'));
    }


    /**
     * Store a new post.
     */
    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'title' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],

            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'excerpt' => [
                'nullable',
                'string',
                'max:500',
            ],

            'content' => [
                'required',
                'string',
                'min:20',
            ],

            'featured_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'images' => [
                'nullable',
                'array',
                'max:10',
            ],

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);


        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Create Post
            |--------------------------------------------------------------------------
            */

            $post = Post::create([
                'user_id' => auth()->id(),
                'category_id' => $request->category_id,
                'title' => $request->title,
                'slug' => generateUniqueSlug(
                    Post::class,
                    $request->title
                ),
                'excerpt' => $request->excerpt,
                'content' => $request->content,
                'status' => 'pending',
                'published_at' => null,
                'views' => 0,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Featured Image
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('featured_image')) {

                $featuredImage = $request
                    ->file('featured_image')
                    ->store('posts/featured', 'public');

                $post->update([
                    'featured_image' => $featuredImage,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Additional Images
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('images')) {

                foreach ($request->file('images') as $key => $image) {

                    $imagePath = $image->store(
                        'posts/images',
                        'public'
                    );

                    PostImage::create([
                        'post_id' => $post->id,
                        'image' => $imagePath,
                        'caption' => null,
                        'sort_order' => $key,
                    ]);
                }
            }


            DB::commit();

            return redirect()
                ->route('posts.index')
                ->with(
                    'success',
                    'Your story has been submitted successfully and is awaiting review.'
                );

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Something went wrong while creating your story.'
                );
        }
    }


    /**
     * Display a single post.
     */
    public function show(Post $post)
    {
        /*
        |--------------------------------------------------------------------------
        | Only published posts are publicly visible
        |--------------------------------------------------------------------------
        */

        if (
            $post->status !== 'published' &&
            $post->user_id !== auth()->id()
        ) {
            abort(404);
        }


        /*
        |--------------------------------------------------------------------------
        | Load Relationships
        |--------------------------------------------------------------------------
        */

        $post->load([
            'user',
            'category',
            'images',
            'comments.user',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Increase Views
        |--------------------------------------------------------------------------
        */

        $post->increment('views');


        return view('posts.show', compact('post'));
    }


    /**
     * Show edit form.
     */
    public function edit(Post $post)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $post->user_id === auth()->id(),
            403
        );


        $categories = Category::where('status', true)
            ->orderBy('name')
            ->get();


        $post->load('images');


        return view(
            'posts.edit',
            compact('post', 'categories')
        );
    }


    /**
     * Update post.
     */
    public function update(Request $request, Post $post)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $post->user_id === auth()->id(),
            403
        );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'title' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],

            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'excerpt' => [
                'nullable',
                'string',
                'max:500',
            ],

            'content' => [
                'required',
                'string',
                'min:20',
            ],

            'featured_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'images' => [
                'nullable',
                'array',
                'max:10',
            ],

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);


        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Update Post
            |--------------------------------------------------------------------------
            */

            $post->update([
                'category_id' => $request->category_id,
                'title' => $request->title,
                'slug' => generateUniqueSlug(
                    Post::class,
                    $request->title,
                    $post->id
                ),
                'excerpt' => $request->excerpt,
                'content' => $request->content,

                /*
                | Send edited post back to moderation.
                */
                'status' => 'pending',
                'published_at' => null,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Featured Image
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('featured_image')) {

                $oldFeaturedImage = $post->featured_image;

                $newFeaturedImage = $request
                    ->file('featured_image')
                    ->store('posts/featured', 'public');


                $post->update([
                    'featured_image' => $newFeaturedImage,
                ]);


                if ($oldFeaturedImage) {

                    Storage::disk('public')
                        ->delete($oldFeaturedImage);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Add New Additional Images
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('images')) {

                $currentImageCount = $post
                    ->images()
                    ->count();

                foreach ($request->file('images') as $key => $image) {

                    $imagePath = $image->store(
                        'posts/images',
                        'public'
                    );

                    PostImage::create([
                        'post_id' => $post->id,
                        'image' => $imagePath,
                        'caption' => null,
                        'sort_order' => $currentImageCount + $key,
                    ]);
                }
            }


            DB::commit();


            return redirect()
                ->route('posts.index')
                ->with(
                    'success',
                    'Your story has been updated successfully and is awaiting review.'
                );

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Something went wrong while updating your story.'
                );
        }
    }


    /**
     * Delete post.
     */
    public function destroy(Post $post)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $post->user_id === auth()->id(),
            403
        );


        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Delete Featured Image
            |--------------------------------------------------------------------------
            */

            if ($post->featured_image) {

                Storage::disk('public')
                    ->delete($post->featured_image);
            }


            /*
            |--------------------------------------------------------------------------
            | Delete Additional Images
            |--------------------------------------------------------------------------
            */

            foreach ($post->images as $image) {

                Storage::disk('public')
                    ->delete($image->image);

                $image->delete();
            }


            /*
            |--------------------------------------------------------------------------
            | Delete Post
            |--------------------------------------------------------------------------
            */

            $post->delete();


            DB::commit();


            return redirect()
                ->route('posts.index')
                ->with(
                    'success',
                    'Your story has been deleted successfully.'
                );

        } catch (Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->with(
                    'error',
                    'Something went wrong while deleting your story.'
                );
        }
    }
}