<x-app-layout>
    <div class="profile-page">
        <article class="sk-card overflow-hidden">
            <div class="profile-cover bg-forest-800">
                @if($profile->coverUrl())
                    <img src="{{ $profile->coverUrl() }}" alt="{{ $profile->name }} cover photo" class="js-lightbox">
                @endif
            </div>
            <div class="profile-main">
                <div class="profile-avatar-row">
                    <x-user-avatar :user="$profile" size="lg" :lightbox="true" />
                    <div class="min-w-0 flex-1 pb-2">
                        <h1 class="truncate text-2xl font-bold text-forest-900">{{ $profile->name }}</h1>
                        @if($profile->username)
                            <p class="text-gray-500">{{ '@'.$profile->username }}</p>
                        @endif
                    </div>
                    @auth
                        @if(auth()->id() === $profile->id)
                            <a href="{{ route('profile.edit') }}" class="btn-primary mb-2 text-sm">Edit profile</a>
                        @elseif($profile->status)
                            <div class="mb-2 flex flex-wrap gap-2">
                                @if($isFollowing)
                                    <form method="POST" action="{{ route('users.follow.destroy', $profile) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-secondary">Unfollow</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('users.follow.store', $profile) }}">
                                        @csrf
                                        <button type="submit" class="btn-primary">{{ $isFollowedBy ? 'Follow back' : 'Follow' }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('messages.direct.store') }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $profile->id }}">
                                    <button type="submit" class="btn-secondary">Message</button>
                                </form>
                            </div>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn-secondary mb-2">Log in to follow</a>
                    @endauth
                </div>

                <div class="profile-stats">
                    <div class="profile-stat"><strong>{{ number_format($profile->score) }}</strong><span>Points</span></div>
                    <div class="profile-stat"><strong>{{ number_format($profile->stories_count) }}</strong><span>Posts</span></div>
                    <div class="profile-stat"><strong>{{ number_format($profile->alerts_count) }}</strong><span>Alerts</span></div>
                    <div class="profile-stat"><strong>{{ number_format($profile->fixes_count) }}</strong><span>Fixes</span></div>
                </div>

                @if($profile->bio)
                    <p class="whitespace-pre-line text-gray-800">{{ $profile->bio }}</p>
                @endif
                @if($profile->location)
                    <p class="mt-2 text-sm text-gray-500">{{ $profile->location }}</p>
                @endif
            </div>
        </article>

        <div class="profile-tabs">
            <a href="{{ route('users.show', ['user' => $profile, 'tab' => 'stories']) }}" class="{{ $tab === 'stories' ? 'active' : '' }}">Posts</a>
            <a href="{{ route('users.show', ['user' => $profile, 'tab' => 'alerts']) }}" class="{{ $tab === 'alerts' ? 'active' : '' }}">Alerts</a>
            <a href="{{ route('users.show', ['user' => $profile, 'tab' => 'activity']) }}" class="{{ $tab === 'activity' ? 'active' : '' }}">Activity</a>
        </div>

        @if($tab === 'stories')
            <div class="profile-grid">
                @forelse($stories as $post)
                    <a href="{{ route('posts.show', $post) }}">
                        @if($post->featured_image)
                            <img src="{{ asset('storage/'.$post->featured_image) }}" alt="{{ $post->title }}">
                        @else
                            <div class="profile-grid-empty">{{ $post->title }}</div>
                        @endif
                    </a>
                @empty
                    <p class="col-span-3 py-10 text-center text-gray-500">No posts yet.</p>
                @endforelse
            </div>
        @elseif($tab === 'alerts')
            <div class="profile-grid">
                @forelse($alerts as $alert)
                    <a href="{{ route('alerts.show', $alert) }}">
                        <img src="{{ asset('storage/'.$alert->featured_image) }}" alt="{{ $alert->title }}">
                    </a>
                @empty
                    <p class="col-span-3 py-10 text-center text-gray-500">No alerts yet.</p>
                @endforelse
            </div>
        @else
            <div class="sk-card p-6">
                @forelse($scoreEvents as $event)
                    <div class="flex justify-between border-b border-gray-100 py-2 text-sm last:border-0">
                        <span>{{ $event->reason->label() }}</span>
                        <span class="font-semibold text-forest-700">+{{ $event->points }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No recent activity.</p>
                @endforelse
            </div>
        @endif
    </div>
</x-app-layout>
