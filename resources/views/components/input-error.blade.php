@props(['messages'])

@php
    $messageList = collect(Illuminate\Support\Arr::flatten((array) $messages))
        ->filter(fn ($message): bool => is_string($message) && $message !== '')
        ->values()
        ->all();
@endphp

@if ($messageList)
    <ul {{ $attributes->merge(['class' => 'sk-error space-y-1']) }}>
        @foreach ($messageList as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
