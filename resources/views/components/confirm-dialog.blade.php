<div
    x-data
    x-cloak
    x-show="$store.confirm.open"
    x-on:keydown.escape.window="$store.confirm.cancel()"
    class="fixed inset-0 z-[90] flex items-center justify-center px-4 py-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="sk-confirm-title"
>
    <div class="absolute inset-0 bg-forest-950/50" x-on:click="$store.confirm.cancel()"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-card">
        <h2 id="sk-confirm-title" class="text-lg font-semibold text-forest-900" x-text="$store.confirm.title"></h2>
        <p class="mt-2 text-sm text-gray-600" x-show="$store.confirm.message" x-text="$store.confirm.message"></p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" class="btn-secondary" x-on:click="$store.confirm.cancel()">Cancel</button>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold text-white"
                :class="$store.confirm.variant === 'primary' ? 'bg-forest-800 hover:bg-forest-700' : 'bg-red-600 hover:bg-red-500'"
                x-text="$store.confirm.actionLabel"
                x-on:click="$store.confirm.accept()"
            ></button>
        </div>
    </div>
</div>
