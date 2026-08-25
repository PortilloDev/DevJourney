<!DOCTYPE html>
<html
    lang="en"
    x-data="{ dark: (localStorage.theme ?? 'dark') === 'dark' }"
    x-init="$watch('dark', v => localStorage.theme = v ? 'dark' : 'light')"
    :class="{ 'dark': dark }"
    class="dark"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $seo->fullTitle(config('app.name')) }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical ?? url()->current() }}">

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:url" content="{{ $seo->canonical ?? url()->current() }}">
    @if($seo->image)
        <meta property="og:image" content="{{ $seo->image }}">
    @endif

    {{-- Twitter --}}
    <meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $seo->title }}">
    <meta name="twitter:description" content="{{ $seo->description }}">
    @if($seo->image)
        <meta name="twitter:image" content="{{ $seo->image }}">
    @endif

    <link rel="alternate" type="application/rss+xml" title="{{ config('app.name') }} feed" href="{{ route('feed') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    @foreach($seo->jsonLd as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200">
    @yield('progress')

    <x-site-header :settings="$settings" />

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    <x-site-footer :settings="$settings" />
</body>
</html>
