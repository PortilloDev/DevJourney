---
title: Database indexes explained for backend engineers
category: Backend
level: B1
tags: databases, sql
excerpt: What a B-tree index really stores, why column order in a composite index matters, and how to read the query plan without guessing.
featured: false
---
## An index is a sorted structure

A database table is a heap of rows. Finding a row by an unindexed column means reading every row until the match appears. An index is a separate structure that keeps the key values sorted and points back to the rows. Because it is sorted, the database can jump to a value instead of scanning. That is the whole trick.

Most relational databases use a B-tree for this. The B-tree keeps keys in order and stays shallow even for millions of rows, so a lookup costs a handful of page reads instead of a full table scan. The index stores the key and a pointer to the row; in some databases it also stores the primary key, and in a covering index it stores additional columns.

## Composite indexes and column order

A composite index on `(customer_id, created_at)` is sorted first by `customer_id` and then by `created_at` within each customer. That means it can serve a query that filters by `customer_id`, or by `customer_id` and a range of `created_at`. It cannot efficiently serve a query that filters only by `created_at`, because the values for different customers are interleaved.

This is the leftmost-prefix rule: an index is useful when the query filters on a prefix of its columns. The order should follow the queries you actually run. If most queries filter by customer and then sort by date, `(customer_id, created_at)` is right. If most queries filter by date across all customers, the order should be reversed.

```sql
create index orders_customer_created
    on orders (customer_id, created_at desc);
```

The `desc` matters for queries that order by `created_at desc`; it lets the database read the index in the requested order instead of sorting the result.

## Selectivity

An index on a column with two values, such as `status`, is often not useful on its own. If half the table is `paid` and half is `pending`, the index still points to a huge number of rows, and the database may choose a sequential scan instead. An index on a high-cardinality column, such as `email` or `order_number`, is much more selective.

Composite indexes can combine a low-cardinality column with a high-cardinality one. `(status, created_at)` can still be useful because the range on `created_at` narrows the result inside the status. The database's statistics decide whether to use it; that is why `ANALYZE` and up-to-date statistics matter.

## Covering indexes

If an index contains every column a query needs, the database can answer from the index alone and never touch the table. This is a covering index. For a query like `select email from users where tenant_id = ?`, an index on `(tenant_id, email)` covers it. The saving can be large when the table is wide or the row is not in cache.

The cost is that the index is wider and writes are more expensive. Covering indexes are a targeted optimization, not a default.

## The write cost

Every index must be updated when a row is inserted, updated or deleted. Ten indexes mean ten structures to maintain. On a write-heavy table, too many indexes can dominate the write latency. I review indexes when a table has more than a handful: each one should correspond to a real query pattern, not to a vague hope.

Indexes also take disk space and memory. A large index competes with the table for the buffer pool. The right number is the smallest set that serves the important queries.

## Reading the plan

`EXPLAIN` is the ground truth. It shows whether the database uses an index, which one, how many rows it estimates, and whether it sorts or uses a temporary table. I look for full scans on large tables, filesorts, and estimated rows that differ wildly from the actual rows.

```sql
explain analyze
select * from orders
where customer_id = 42
order by created_at desc
limit 20;
```

`explain analyze` actually runs the query and shows the real timings. If the plan uses the index and the rows examined are close to the rows returned, the query is in good shape.

## Common mistakes

The first is applying a function to an indexed column: `where date(created_at) = '2026-01-01'` cannot use a plain index on `created_at` because the function changes the value. Rewrite it as a range: `created_at >= '2026-01-01' and created_at < '2026-01-02'`.

The second is a leading wildcard: `where email like '%@example.com'` cannot use a B-tree efficiently. A suffix search needs a different structure, such as a trigram index or a search engine.

The third is a type mismatch. Comparing a string column to a number can force an implicit cast and disable the index. Keep the types aligned.

The fourth is `OR` across different columns. The database may use two indexes and combine them, or it may scan. If the query is hot, rewrite it as a `UNION` of two indexed queries and measure.

## Partial and expression indexes

Some databases support partial indexes, which index only the rows that match a condition. If most orders are archived and the hot query only touches active orders, a partial index on `status = 'active'` is smaller and faster than a full index. Expression indexes go further: they index the result of an expression, such as a lowercased email, so a case-insensitive lookup can still use an index. Both are tools to check in the database you actually use; they are not portable, so I use them only when the query justifies the coupling.

## When not to index

Do not index a small table that fits in memory; the scan is already fast. Do not index a column you never filter or sort by. Do not add an index for a report that runs once a month if it slows down every write. And do not assume an index is used just because it exists; check the plan.

## Conclusion

An index is a sorted map from key to row. Composite indexes follow the leftmost-prefix rule, selectivity decides whether the database bothers, and covering indexes trade write cost for read speed. The plan is the only reliable judge. Measure the query, add the smallest index that fixes it, and remove the ones that no longer earn their keep.
