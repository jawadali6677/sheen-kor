<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $profile->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="profile-page px-4 sm:px-6">
            <article class="profile-card">
                <div class="profile-cover">
                    @if($profile->coverUrl())
                        <img src="{{ $profile->coverUrl() }}" alt="{{ $profile->name }} cover photo" class="js-lightbox">
                    @endif
                </div>

                <div class="profile-main">
                    <div class="profile-avatar-row">
                        <x-user-avatar :user="$profile" size="lg" :lightbox="true" />
                        <div class="flex-1 min-w-0 pb-2">
                            <h1 class="text-2xl font-bold text-gray-900 truncate">{{ $profile->name }}</h1>
                            @if($profile->username)
                                <p class="text-gray-500">{{ '@'.$profile->username }}</p>
                            @endif
                        </div>
                        @if(auth()->id() === $profile->id)
                            <a href="{{ route('profile.edit') }}" class="mb-2 px-4 py-2 bg-gray-800 text-white rounded text-sm">
                                Edit profile
                            </a>
                        @elseif($profile->status)
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                @if($isFollowing)
                                    <form method="POST" action="{{ route('users.follow.destroy', $profile) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-4 py-2 border border-gray-300 text-gray-800 rounded text-sm">
                                            Unfollow
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('users.follow.store', $profile) }}">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded text-sm">
                                            {{ $isFollowedBy ? 'Follow back' : 'Follow' }}
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('messages.direct.store') }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $profile->id }}">
                                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded text-sm">
                                        Message
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>

                    <div class="profile-stats">
                        <div class="profile-stat">
                            <strong>{{ number_format($profile->score) }}</strong>
                            <span>Points</span>
                        </div>
                        <div class="profile-stat">
                            <strong>{{ number_format($profile->stories_count) }}</strong>
                            <span>Stories</span>
                        </div>
                        <div class="profile-stat">
                            <strong>{{ number_format($profile->alerts_count) }}</strong>
                            <span>Alerts</span>
                        </div>
                        <div class="profile-stat">
                            <strong>{{ number_format($profile->fixes_count) }}</strong>
                            <span>Fixes</span>
                        </div>
                    </div>

                    <p class="text-sm text-gray-500 mb-2">{{ $profile->roleLabel() }}</p>

                    @if($profile->bio)
                        <p class="text-gray-800 whitespace-pre-line">{{ $profile->bio }}</p>
                    @endif

                    <div class="mt-3 text-sm text-gray-500 space-y-1">
                        @if($profile->location)
                            <p>{{ $profile->location }}</p>
                        @endif
                        @if($profile->website)
                            <p>
                                <a href="{{ $profile->website }}" class="text-blue-700" target="_blank" rel="noopener noreferrer">
                                    {{ $profile->website }}
                                </a>
                            </p>
                        @endif
                    </div>
                </div>
            </article>

            @if($scoreEvents->isNotEmpty())
                <div class="profile-card p-4 sm:p-6">
                    <h2 class="font-semibold text-gray-900 mb-3">Recent points</h2>
                    @foreach($scoreEvents as $event)
                        <div class="flex justify-between text-sm py-2 border-b last:border-0">
                            <span>{{ $event->reason->label() }}</span>
                            <span class="font-semibold text-green-700">+{{ $event->points }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="profile-tabs">
                <a href="{{ route('users.show', ['user' => $profile, 'tab' => 'stories']) }}" class="{{ $tab === 'stories' ? 'active' : '' }}">
                    Stories
                </a>
                <a href="{{ route('users.show', ['user' => $profile, 'tab' => 'alerts']) }}" class="{{ $tab === 'alerts' ? 'active' : '' }}">
                    Alerts
                </a>
            </div>

            @if($tab === 'stories')
                <div class="profile-grid">
                    @forelse($stories as $post)
                        <a href="{{ route('posts.show', $post) }}">
                            @if($post->featured_image)
                                <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}">
                            @else
                                <div class="profile-grid-empty">{{ $post->title }}</div>
                            @endif
                        </a>
                    @empty
                        <p class="col-span-3 text-center text-gray-500 py-10">No stories yet.</p>
                    @endforelse
                </div>
            @else
                <div class="profile-grid">
                    @forelse($alerts as $alert)
                        <a href="{{ route('alerts.show', $alert) }}">
                            <img src="{{ asset('storage/' . $alert->featured_image) }}" alt="{{ $alert->title }}">
                        </a>
                    @empty
                        <p class="col-span-3 text-center text-gray-500 py-10">No alerts yet.</p>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
