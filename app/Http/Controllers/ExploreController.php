<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExploreController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString();

        if (! in_array($tab, ['posts', 'people', 'alerts', 'categories'], true)) {
            $tab = 'posts';
        }

        $search = trim((string) $request->input('q', ''));
        $like = $search === '' ? null : '%'.addcslashes($search, '%_\\').'%';

        $posts = Post::query()
            ->with(['user', 'category'])
            ->where('status', 'published')
            ->when($like, function ($query) use ($like): void {
                $query->where(function ($query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('content', 'like', $like);
                });
            })
            ->latest('published_at')
            ->latest('id')
            ->limit(12)
            ->get();

        $alerts = Alert::query()
            ->with('user')
            ->when($like, function ($query) use ($like): void {
                $query->where(function ($query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('location_name', 'like', $like);
                });
            })
            ->latest('created_at')
            ->limit(12)
            ->get();

        $people = User::query()
            ->where('status', true)
            ->when($like, function ($query) use ($like): void {
                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        $categories = Category::query()
            ->where('status', true)
            ->when($like, function ($query) use ($like): void {
                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->orderBy('name')
            ->get();

        return view('explore.index', compact(
            'tab',
            'search',
            'posts',
            'alerts',
            'people',
            'categories',
        ));
    }
}
