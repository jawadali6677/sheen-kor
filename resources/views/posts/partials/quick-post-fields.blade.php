@php
    $selectedCategory = (string) old('category_id', $post?->category_id ?? '');
    $textFieldId = $post ? 'edit-post-text-'.$post->id : 'quick-post-text';
    $composerErrors = collect($errors->all())
        ->map(fn (string $message): string => friendly_post_message($message))
        ->filter()
        ->values();
@endphp

<form
    x-ref="form"
    action="{{ $post ? route('posts.update', $post) : route('posts.store') }}"
    method="POST"
    enctype="multipart/form-data"
    class="{{ $formClass }}"
    @submit="onSubmit($event)"
>
    @csrf
    @if($post)
        @method('PUT')
    @endif
    <input type="hidden" name="simple_post" value="1">

    @unless($embedded)
        <div class="mx-auto mb-3 h-1.5 w-12 rounded-full bg-gray-200 lg:hidden"></div>
    @endunless

    <div class="mb-4 flex items-center justify-between gap-3">
        @if($embedded)
            <a href="{{ route('posts.index') }}" class="text-sm font-semibold text-gray-500">Cancel</a>
        @else
            <button type="button" class="text-sm font-semibold text-gray-500" @click="close()">Close</button>
        @endif
        <h2 class="text-base font-semibold text-forest-900">{{ $post ? 'Edit post' : 'Create post' }}</h2>
        <a href="{{ $post ? route('posts.edit', $post).'?advanced=1' : route('posts.create') }}" class="text-sm font-semibold text-forest-800">More options</a>
    </div>

    <label for="{{ $textFieldId }}" class="sr-only">What's on your mind?</label>
    <textarea
        x-ref="body"
        id="{{ $textFieldId }}"
        name="content"
        rows="3"
        x-model="text"
        @input="grow($event)"
        placeholder="What's on your mind?"
        class="max-h-56 w-full resize-none rounded-2xl border-0 bg-sand-50 px-4 py-3 text-lg leading-7 text-gray-900 placeholder:text-gray-400 focus:border-forest-600 focus:ring-forest-600"
    >{{ old('content', $post->content ?? '') }}</textarea>

    <div class="mt-4 grid grid-cols-3 gap-2">
        <button type="button" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800" @click="$refs.photoPicker.click()">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 16 4.6-4.6a2 2 0 0 1 2.8 0L16 16m-2-2 1.6-1.6a2 2 0 0 1 2.8 0L20 14M8 8h.01M6 20h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/></svg>
            Photo
        </button>
        <button type="button" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800" @click="$refs.videoPicker.click()">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 10 4.6-2.3A1 1 0 0 1 21 8.6v6.8a1 1 0 0 1-1.4.9L15 14M4 8h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2Z"/></svg>
            Video
        </button>
        <button type="button" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800" @click="$refs.cameraPicker.click()">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h2.5l1.2-2h8.6l1.2 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
            Camera
        </button>
    </div>

    <input x-ref="photoPicker" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" multiple @change="onPickerChange($event)">
    <input x-ref="videoPicker" type="file" class="sr-only" accept="video/mp4,video/webm,video/quicktime" multiple @change="onPickerChange($event)">
    <input x-ref="cameraPicker" type="file" class="sr-only" accept="image/*" capture="environment" @change="onPickerChange($event)">

    <input x-ref="featured" type="file" data-field-name="featured_image" class="hidden" accept="image/jpeg,image/png,image/webp" tabindex="-1">
    <input x-ref="images" type="file" data-field-name="images[]" class="hidden" multiple accept="image/jpeg,image/png,image/webp" tabindex="-1">
    <input x-ref="videos" type="file" data-field-name="videos[]" class="hidden" multiple accept="video/mp4,video/webm,video/quicktime" tabindex="-1">

    @if($post && $post->hasMedia())
        <div class="mt-4">
            <p class="text-sm font-semibold text-gray-600">Your photos and videos</p>
            <ul class="mt-2 grid grid-cols-3 gap-3">
                @if($post->featured_image)
                    <li class="relative">
                        <input id="remove-featured-{{ $post->id }}" type="checkbox" name="remove_featured" value="1" class="peer sr-only">
                        <img src="{{ asset('storage/'.$post->featured_image) }}" alt="Photo" class="h-28 w-full rounded-2xl object-cover peer-checked:opacity-40">
                        <label for="remove-featured-{{ $post->id }}" class="absolute right-1 top-1 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-black/70 text-lg font-semibold text-white peer-checked:bg-red-700" aria-label="Remove photo">✕</label>
                    </li>
                @endif
                @foreach($post->images as $image)
                    <li class="relative">
                        <input id="remove-media-{{ $image->id }}" type="checkbox" name="remove_media[]" value="{{ $image->id }}" class="peer sr-only">
                        @if($image->isVideo())
                            <video src="{{ asset('storage/'.$image->image) }}" class="h-28 w-full rounded-2xl object-cover peer-checked:opacity-40" muted playsinline></video>
                        @else
                            <img src="{{ asset('storage/'.$image->image) }}" alt="Photo" class="h-28 w-full rounded-2xl object-cover peer-checked:opacity-40">
                        @endif
                        <label for="remove-media-{{ $image->id }}" class="absolute right-1 top-1 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-black/70 text-lg font-semibold text-white peer-checked:bg-red-700" aria-label="Remove">✕</label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <ul class="mt-4 grid grid-cols-3 gap-3" x-show="items.length" x-cloak>
        <template x-for="item in items" :key="item.id">
            <li class="relative">
                <img x-show="item.kind === 'image'" :src="item.url" alt="" class="h-28 w-full rounded-2xl object-cover">
                <video x-show="item.kind === 'video'" :src="item.url" class="h-28 w-full rounded-2xl object-cover" muted playsinline></video>
                <button type="button" class="absolute right-1 top-1 flex h-9 w-9 items-center justify-center rounded-full bg-black/70 text-lg font-semibold text-white" @click="removeItem(item.id)" aria-label="Remove">✕</button>
            </li>
        </template>
    </ul>

    @if($categories->isNotEmpty())
        <div class="mt-5">
            <p class="text-sm font-semibold text-gray-600">Topic</p>
            <p class="mt-1 text-sm text-gray-500">Pick one if you want. You can skip this.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($categories as $category)
                    <label class="cursor-pointer">
                        <input
                            type="radio"
                            name="category_id"
                            value="{{ $category->id }}"
                            class="peer sr-only"
                            @checked($selectedCategory === (string) $category->id)
                        >
                        <span class="inline-flex min-h-11 items-center rounded-full border border-gray-200 bg-white px-4 py-2 text-base font-semibold text-forest-900 peer-checked:border-forest-800 peer-checked:bg-forest-800 peer-checked:text-white">{{ $category->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-4 space-y-2" x-show="errors.length" x-cloak>
        <template x-for="item in errors" :key="item">
            <p class="text-sm font-medium text-red-700" data-composer-error x-text="item" role="alert"></p>
        </template>
    </div>

    <noscript>
        @if($composerErrors->isNotEmpty())
            <div class="mt-4 space-y-1" role="alert">
                @foreach($composerErrors as $message)
                    <p class="text-sm font-medium text-red-700" data-composer-error>{{ $message }}</p>
                @endforeach
            </div>
        @endif
    </noscript>

    <div x-show="phase === 'uploading' || phase === 'checking'" x-cloak class="mt-4 space-y-2" role="status" aria-live="polite">
        <div class="h-2.5 overflow-hidden rounded-full bg-sand-100">
            <div class="h-full rounded-full bg-lime-400 transition-all" :style="`width: ${progress}%`"></div>
        </div>
        <p class="text-center text-sm font-semibold text-forest-800" x-show="phase === 'uploading'">Uploading... <span x-text="progress"></span>%</p>
        <p class="text-center text-sm font-semibold text-forest-800" x-show="phase === 'checking'" data-composer-checking>Checking your post...</p>
    </div>

    <p class="mt-4 rounded-2xl bg-forest-50 px-4 py-3 text-center text-sm font-semibold text-forest-800" x-show="phase === 'success'" x-cloak x-text="success" role="status"></p>

    <div class="mt-4" @click="tryPost()">
        <button type="submit" class="btn-primary pointer-events-none w-full py-3.5 text-base" :disabled="submitting || ! canPost">Post</button>
    </div>
</form>
