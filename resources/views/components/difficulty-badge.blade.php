@props(['difficulty'])

@php
    /** @var \App\Enums\ChallengeDifficulty $difficulty */
    $difficulty = $difficulty instanceof \App\Enums\ChallengeDifficulty
        ? $difficulty
        : \App\Enums\ChallengeDifficulty::from($difficulty);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset ' . $difficulty->badgeClasses()]) }}>
    {{ $difficulty->label() }}
</span>
