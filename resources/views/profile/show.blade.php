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
                        <h1 class="flex items-center gap-2 truncate text-2xl font-bold text-forest-900">
                            <span class="truncate">{{ $profile->name }}</span>
                            <x-green-tick :user="$profile" />
                        </h1>
                        @if($profile->username)
                            <p class="text-gray-500">{{ '@'.$profile->username }}</p>
                        @endif
                    </div>
                    @auth
                        @if(auth()->id() === $profile->id)
                            <a href="{{ route('profile.edit') }}" class="profile-edit-icon mb-2" aria-label="Edit profile">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                </svg>
                            </a>
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
            <a href="{{ route('users.show', ['user' => $profile, 'tab' => 'fixes']) }}" class="{{ $tab === 'fixes' ? 'active' : '' }}">Fixes</a>
            <a href="{{ route('users.show', ['user' => $profile, 'tab' => 'market']) }}" class="{{ $tab === 'market' ? 'active' : '' }}">Market</a>
            <a href="{{ route('users.show', ['user' => $profile, 'tab' => 'activity']) }}" class="{{ $tab === 'activity' ? 'active' : '' }}">Activity</a>
        </div>

        @if($tab === 'stories')
            <div class="profile-grid">
                @forelse($stories as $post)
                    <a
                        href="{{ route('posts.show', $post) }}"
                        @auth
                            data-qualified-view-post="{{ $post->id }}"
                            data-qualified-view-author="{{ $post->user_id }}"
                        @endauth
                    >
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
        @elseif($tab === 'fixes')
            <div class="space-y-3">
                @forelse($claimedAlerts as $alert)
                    @php
                        $actionDate = $alert->isFixed() ? $alert->fixed_at : $alert->action_taken_at;
                    @endphp
                    <a href="{{ route('alerts.show', $alert) }}" class="sk-card flex flex-col gap-2 p-4 hover:bg-forest-50/40">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-base font-semibold text-forest-900">{{ $alert->title }}</h2>
                            <x-status-badge :alert="$alert" />
                        </div>
                        <p class="text-sm text-gray-500">
                            {{ $alert->location_name }}
                            @if($actionDate)
                                · {{ $actionDate->format('M d, Y') }}
                            @endif
                        </p>
                    </a>
                @empty
                    <p class="py-10 text-center text-gray-500">No fixes yet.</p>
                @endforelse
            </div>
        @elseif($tab === 'market')
            <div class="profile-grid">
                @forelse($marketListings as $listing)
                    <a href="{{ route('market.show', $listing) }}">
                        @if($listing->featured_image)
                            <img src="{{ asset('storage/'.$listing->featured_image) }}" alt="{{ $listing->title }}">
                        @else
                            <div class="profile-grid-empty">{{ $listing->title }}</div>
                        @endif
                    </a>
                @empty
                    <p class="col-span-3 py-10 text-center text-gray-500">No market listings yet.</p>
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
