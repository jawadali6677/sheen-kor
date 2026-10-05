<x-app-layout>
    <div class="space-y-4">
        <h1 class="text-xl font-semibold text-forest-900">{{ __('Profile') }}</h1>

        <div class="sk-card p-4 sm:p-8">
            <div class="max-w-2xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="sk-card p-4 sm:p-8">
            <div class="max-w-2xl">
                @include('profile.partials.green-tick')
            </div>
        </div>

        <div class="sk-card p-4 sm:p-8">
            <div class="max-w-2xl">
                @include('profile.partials.rewarded-ad')
            </div>
        </div>

        <div class="sk-card p-4 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="sk-card p-4 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
