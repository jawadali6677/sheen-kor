<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Complete your profile') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ $user->profileCompletionPercent() }}% complete · {{ $user->roleLabel() }} · {{ number_format($user->score) }} points
        </p>
        <p class="mt-1 text-sm">
            <a href="{{ route('users.show', $user) }}" class="text-blue-700">{{ __('View public profile') }}</a>
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="cover_image" :value="__('Cover photo')" />
            @if($user->coverUrl())
                <img id="cover-preview" src="{{ $user->coverUrl() }}" alt="Cover photo" class="profile-edit-cover mt-2 js-lightbox">
                <label class="mt-2 inline-flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="remove_cover_image" value="1" class="rounded border-gray-300">
                    <span class="ms-2">{{ __('Remove cover photo') }}</span>
                </label>
            @else
                <img id="cover-preview" src="" alt="Cover photo preview" class="profile-edit-cover mt-2 js-lightbox hidden">
            @endif
            <input id="cover_image" name="cover_image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm" data-preview-target="#cover-preview">
            <x-input-error class="mt-2" :messages="$errors->get('cover_image')" />
        </div>

        <div>
            <x-input-label for="profile_image" :value="__('Profile picture')" />
            <div class="mt-2 flex items-center gap-4">
                <div id="current-avatar">
                    <x-user-avatar :user="$user" :lightbox="true" />
                </div>
                <img id="profile-image-preview" src="" alt="New profile picture" class="profile-edit-preview js-lightbox hidden">
            </div>
            @if($user->profile_image)
                <label class="mt-2 inline-flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="remove_profile_image" value="1" class="rounded border-gray-300">
                    <span class="ms-2">{{ __('Remove profile picture') }}</span>
                </label>
            @endif
            <input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm" data-preview-target="#profile-image-preview" data-preview-hide="#current-avatar">
            <p class="text-xs text-gray-500 mt-1">JPEG, PNG, or WebP. Maximum 5 MB.</p>
            <x-input-error class="mt-2" :messages="$errors->get('profile_image')" />
        </div>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $user->username)" autocomplete="username" />
            <p class="text-xs text-gray-500 mt-1">Letters, numbers, dots, and underscores. This is shown on your public profile.</p>
            <x-input-error class="mt-2" :messages="$errors->get('username')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="email" />
            <p class="text-xs text-gray-500 mt-1">{{ __('Your email stays private.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="bio" :value="__('Bio')" />
            <textarea id="bio" name="bio" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" maxlength="500" placeholder="Tell people about you and the work you do for the environment.">{{ old('bio', $user->bio) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        <div>
            <x-input-label for="location" :value="__('Location')" />
            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" :value="old('location', $user->location)" placeholder="City, country" />
            <x-input-error class="mt-2" :messages="$errors->get('location')" />
        </div>

        <div>
            <x-input-label for="website" :value="__('Website')" />
            <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $user->website)" placeholder="https://" />
            <x-input-error class="mt-2" :messages="$errors->get('website')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
