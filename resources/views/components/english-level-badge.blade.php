@props(['level'])

@php
    /** @var \App\Enums\EnglishLevel $level */
    $level = $level instanceof \App\Enums\EnglishLevel
        ? $level
        : \App\Enums\EnglishLevel::from($level);
@endphp

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset ' . $level->badgeClasses()]) }}
    title="English level at writing time: {{ $level->label() }}"
>
    {{ $level->value }}
</span>
