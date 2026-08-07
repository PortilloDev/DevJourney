{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('app.name') }}</title>
        <link>{{ url('/') }}</link>
        <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml" />
        <description>Learning journal &amp; portfolio of a senior backend developer.</description>
        <language>en</language>
        @if($updatedAt)
            <lastBuildDate>{{ $updatedAt->toRssString() }}</lastBuildDate>
        @endif
        @foreach($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ route('posts.show', $post) }}</link>
                <guid isPermaLink="true">{{ route('posts.show', $post) }}</guid>
                <pubDate>{{ $post->published_at?->toRssString() }}</pubDate>
                @if($post->category)
                    <category>{{ $post->category->name }}</category>
                @endif
                <description>{{ $post->excerpt ?? \Illuminate\Support\Str::of($post->body_md)->stripTags()->limit(300) }}</description>
            </item>
        @endforeach
    </channel>
</rss>
