---
title: N+1 queries and the eager loading habit
category: Backend
level: B1
tags: laravel, databases
excerpt: Why a page with 100 posts fires 101 queries, how eager loading fixes it, and when loading everything at once becomes the new problem.
featured: false
---
## The query that hides in the loop

The N+1 problem is one of the most common performance bugs in applications that use an ORM. It happens when you load a list of N records and then, for each record, trigger an additional query to load a relationship. The page works. The data is correct. The database receives N+1 queries instead of two, and the latency grows with the size of the list.

In Laravel the default behaviour is lazy loading. Accessing `$post->category` on a post that was not eager-loaded triggers a query at that moment. It is convenient in a controller with one post and disastrous in a Blade loop with a hundred.

```php
$posts = Post::query()->latest()->take(100)->get();

foreach ($posts as $post) {
    echo $post->category->name; // one query per post
}
```

The first query loads the posts. Then each iteration loads one category. If the category is null, it still queries. A hundred posts mean a hundred and one queries, and the page time is dominated by the round trips.

## Eager loading

The fix is to tell the ORM which relationships you need before iterating. In Laravel that is `with()`:

```php
$posts = Post::query()
    ->with('category')
    ->latest()
    ->take(100)
    ->get();
```

Now Laravel runs one query for the posts and one `where id in (...)` query for the categories, regardless of the number of posts. Two queries instead of a hundred and one. The result set is the same; the access pattern is different.

You can eager-load nested relationships with dot notation: `with('category.parent')`, `with('tags')`, `with('comments.author')`. Each level adds one query, not one per row. I keep an eye on the number of levels; three or four are usually fine, ten mean the page is doing too much.

## Constrained eager loads

Sometimes you need only part of a relationship. A post may have hundreds of comments, and the page shows the latest five. Eager-loading all of them would transfer far more data than the page needs.

```php
$posts = Post::query()
    ->with(['comments' => fn ($query) => $query->latest()->limit(5)])
    ->get();
```

Laravel supports constraints on the eager load. The query still runs once, with the constraint applied per parent through a window function or a subquery, depending on the version. The point is the same: define the shape before the loop.

## Counts without loading

If the page only needs the number of comments, loading the comments is wasteful. `withCount()` adds a subquery that returns the count as an attribute.

```php
$posts = Post::query()->withCount('comments')->get();

echo $posts->first()->comments_count;
```

This is especially useful for badges and sorting: `orderByDesc('comments_count')` can be done in the same query. A common mistake is to load the full relation and call `count()` in the view; the database transfers every row just to count them.

## Detecting N+1

The easiest detection is to log queries in development and count them per request. Laravel's query log, Laravel Debugbar or Telescope all show the queries and their bindings. If a page with 50 rows has 51 queries, the pattern is obvious.

I also like tests. A feature test can enable the query log, hit the page, and assert that the number of queries stays below a threshold. The threshold is a budget: it catches regressions without asserting an exact count that changes every time someone adds a join.

```php
DB::enableQueryLog();
$this->get('/journal')->assertOk();
$this->assertLessThan(20, count(DB::getQueryLog()));
```

A budget test is not a substitute for profiling, but it stops the obvious N+1 from coming back after a refactor.

## Lazy loading in serialization

A subtle case is serialization. When an API resource converts a model to an array, it may touch relationships that were never loaded. The N+1 appears in the JSON response, not in the Blade view. The fix is the same: eager-load before returning the resource, or use a resource class that explicitly declares the fields it needs. I also check pagination endpoints, where each item in a page can hide a relationship. A query budget test on the API route catches it before the mobile client does.

## Eager loading in chunks

For exports or background jobs that process thousands of rows, `with()` on the whole set can consume a lot of memory. `chunk()` or `cursor()` limits the parents, and the eager load applies per chunk. The query count grows with the number of chunks, but each chunk stays bounded. That is the right trade-off for a job that must not exhaust memory.

## When eager loading hurts

Eager loading is not free. If a post has ten thousand comments and the page shows all of them, loading them in one query is still a lot of data. The answer is pagination or a constrained load, not a different loading strategy.

A huge `where id in (...)` can also hit a parameter limit or a query planner that chooses a sequential scan. For very large sets, chunking the parents and loading the children per chunk can be better. The rule is to match the query to the page: load what you render, no more.

## The habit

I try to make eager loading a habit rather than a fix. When I write a query that will feed a view, I ask: which relationships does the view touch? Then I load them. When a relationship is added to the view later, the query is the first place I look.

Laravel also offers `Model::preventLazyLoading()` in non-production environments. It throws an exception when a relationship is accessed without being eager-loaded. That is a strong forcing function: it turns a silent performance bug into a visible error during development. I recommend enabling it in local and CI, and leaving it off in production where a lazy load is better than a broken page.

## Conclusion

N+1 is a shape problem, not an ORM problem. The loop asks for data one row at a time; eager loading asks for the whole relationship at once. Use `with()` for what you render, `withCount()` for counts, and constrained loads for large relations. Add a query budget to your tests so the fix stays fixed. The page will not only be faster; it will also scale with the size of the list instead of against it.
