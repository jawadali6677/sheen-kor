@php
    $reopenComposer = $errors->any()
        && (string) old('simple_post') === '1'
        && ! request()->routeIs('posts.edit', 'posts.create');
    $composerErrorMessages = $reopenComposer
        ? collect($errors->all())->map(fn (string $message): string => friendly_post_message($message))->values()->all()
        : [];
@endphp

<div
    x-data="quickPostComposer({
        embedded: false,
        text: @js($reopenComposer ? (string) old('content', '') : ''),
        open: @js($reopenComposer),
        errors: @js($composerErrorMessages),
    })"
    x-on:open-post-composer.window="openWith($event.detail)"
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[80] flex items-end justify-center lg:items-center"
        @keydown.escape.window="close()"
    >
        <div class="absolute inset-0 bg-forest-950/50" @click="close()"></div>
        <div class="relative z-10 w-full lg:max-w-lg" role="dialog" aria-modal="true" aria-label="Create post">
            @include('posts.partials.quick-post-fields', [
                'post' => null,
                'categories' => $composerCategories,
                'embedded' => false,
                'formClass' => 'relative max-h-[92vh] overflow-y-auto rounded-t-3xl border border-emerald-100 bg-white px-4 pb-6 pt-3 shadow-card lg:max-h-[85vh] lg:rounded-3xl lg:border-forest-100 lg:bg-gradient-to-b lg:from-forest-50 lg:to-white lg:p-6',
            ])
        </div>
    </div>
</div>
