@if($promotion = $listing->currentOrLatestPromotion())
    <p class="font-medium text-gray-800">{{ $promotion->placement->label() }}</p>
    <p class="text-gray-500">{{ $promotion->displayStatus()->label() }}</p>
    @if($activeUntil = $promotion->activeUntilPhrase())
        <p class="text-gray-500">{{ $activeUntil }}</p>
    @endif
@else
    <span class="text-gray-400">None</span>
@endif
