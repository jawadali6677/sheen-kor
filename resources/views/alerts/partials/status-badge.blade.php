@php
    $status = $alert->status;
@endphp

@if($status === 'fixed')
    <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-green-100 text-green-700 font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
        </svg>
        Fixed
    </span>
@elseif($status === 'in_progress')
    <span class="inline-flex items-center px-2 py-1 rounded bg-blue-100 text-blue-700 font-medium">
        In progress
    </span>
@else
    <span class="inline-flex items-center px-2 py-1 rounded bg-amber-100 text-amber-800 font-medium">
        Open
    </span>
@endif
