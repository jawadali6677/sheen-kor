@props(['user'])

@if($user->hasActiveGreenTick())
    <span {{ $attributes->merge(['class' => 'inline-flex h-4 w-4 items-center justify-center rounded-full bg-forest-700 text-[10px] font-bold text-lime-300', 'title' => 'Green Tick verified']) }} aria-label="Green Tick verified">✓</span>
@endif
