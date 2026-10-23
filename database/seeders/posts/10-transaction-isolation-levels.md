---
title: Transaction isolation levels without the hand-waving
category: Backend
level: B2
tags: databases, sql
excerpt: Dirty reads, phantom rows and lost updates explained through the anomalies they prevent — and the locking tools Laravel gives you.
featured: false
---
## ACID is a promise with levels

A transaction is supposed to be atomic, consistent, isolated and durable. Atomicity, consistency and durability are mostly binary: the transaction commits or it does not, the data respects the constraints, and the commit survives a crash. Isolation is different. It is a spectrum, and the level you choose decides which anomalies your application can see.

The SQL standard defines four levels. Each one prevents a set of anomalies at the cost of concurrency. The names are not intuitive; the anomalies are clearer.

## The anomalies

A **dirty read** happens when a transaction reads data written by another transaction that has not committed. If the other transaction rolls back, the first one acted on a value that never existed.

A **non-repeatable read** happens when a transaction reads the same row twice and gets different values because another transaction committed a change in between. The row did not change from the reader's point of view, but the world did.

A **phantom read** happens when a transaction runs the same query twice and gets a different set of rows because another transaction inserted or deleted rows that match the predicate. It is a non-repeatable read at the level of a result set.

A **lost update** happens when two transactions read the same value, both compute a new value, and the second overwrites the first. The first update is lost even though both transactions committed.

## The four levels

**Read uncommitted** allows dirty reads. It is rarely useful; the performance gain is small and the bugs are severe. Most databases do not implement it as a separate mode in practice.

**Read committed** prevents dirty reads but allows non-repeatable reads and phantoms. Each statement sees a fresh snapshot of committed data. This is the default in PostgreSQL and a common choice for web applications because it keeps concurrency high.

**Repeatable read** prevents non-repeatable reads by keeping a snapshot for the whole transaction. In MySQL InnoDB it also prevents most phantoms through next-key locking, but the exact behaviour is subtle. This is MySQL's default.

**Serializable** makes transactions behave as if they ran one after another. It prevents all the anomalies above, including write skew, at the cost of more locking or more aborts. It is the safest level and the least concurrent.

## MySQL InnoDB in practice

InnoDB's repeatable read uses a consistent snapshot for plain `SELECT` statements. A plain select does not lock rows; it reads the snapshot. A locking read, such as `SELECT ... FOR UPDATE`, reads the latest committed version and takes locks. This distinction matters: two transactions can both read the same row with a plain select and then both try to update it, and the second update may block or produce a lost update depending on the write.

For a counter, the safe pattern is an atomic update or a locking read:

```sql
update accounts set balance = balance - 100 where id = 1;
```

The database applies the arithmetic to the current value, so two concurrent decrements do not overwrite each other. If the logic needs to read, decide and write, use `SELECT ... FOR UPDATE` inside the transaction to serialize the access.

Laravel exposes both:

```php
DB::transaction(function () {
    $account = Account::query()->lockForUpdate()->find(1);

    if ($account->balance < 100) {
        throw new InsufficientFunds();
    }

    $account->decrement('balance', 100);
});
```

`lockForUpdate()` adds `FOR UPDATE`; `sharedLock()` adds `FOR SHARE` for readers that must not see a concurrent change. The lock is held until the transaction commits or rolls back, so the transaction should be short.

## Deadlocks

When two transactions lock rows in opposite orders, each waits for the other. The database detects the cycle and kills one transaction with a deadlock error. The application must retry that transaction.

The best defence is to lock rows in a consistent order and keep transactions short. If transaction A updates account 1 then 2, transaction B should also update 1 then 2, not 2 then 1. When a deadlock happens, retry the whole transaction with a bounded number of attempts and a small backoff. Do not retry forever.

Laravel's `DB::transaction()` accepts an attempts argument:

```php
DB::transaction(function () {
    // ...
}, 3);
```

That retries on deadlock up to three times. It is not a substitute for consistent lock ordering, but it turns a rare error into a successful request.

## Choosing a level

For most web applications, read committed is enough. It avoids dirty reads, keeps concurrency high, and the remaining anomalies are rare in short transactions. If the application relies on reading the same rows multiple times inside a transaction and expecting stability, repeatable read is a better fit. Serializable is for cases where correctness outweighs throughput, such as financial invariants that span multiple rows.

The important thing is to know which anomalies your code can tolerate. A report that sums rows twice in one transaction may prefer repeatable read. A queue consumer that processes one row at a time may be fine with read committed. The level is a design decision, not a default to accept blindly.

## Savepoints and partial rollbacks

Laravel's `DB::transaction()` can create a savepoint inside an outer transaction. That lets you attempt an optional operation and roll it back without aborting the whole unit of work. It is useful for a batch where one item may fail but the rest should commit. The savepoint does not change isolation, but it changes how much work a failure discards. I use it sparingly: if a batch can partially succeed, the caller must know which items were committed.

## Conclusion

Isolation levels are a menu of anomalies. Read committed prevents dirty reads; repeatable read stabilizes a snapshot; serializable removes the rest at a cost. InnoDB adds locking reads and next-key locks that change the details. Use atomic updates for counters, lock rows when the logic needs read-decide-write, keep transactions short, and retry deadlocks. The goal is not the strictest level; it is the weakest level that keeps your invariants true.
