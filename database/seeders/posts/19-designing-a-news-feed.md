---
title: Designing a news feed at scale
category: System Design
level: C1
tags: interviews, system-design
excerpt: Fan-out on write, fan-out on read and the hybrid that handles celebrities. A system design walkthrough with the trade-offs interviewers probe.
featured: false
---
## The problem

Design a news feed: users follow other users, and when they open the app they see recent posts from the people they follow, ranked by relevance. The naive version is a join: select posts from the people I follow, order by time, limit 20. It works for a thousand users. It does not work for hundreds of millions, because the join touches a different set of rows for every user and the read traffic is enormous.

The interview is about the shape of the data and the trade-off between writing the feed when a post is created and reading it when the user opens the app. I start with requirements and numbers, then choose a fan-out strategy.

## Requirements and numbers

Functional: publish a post, follow and unfollow, read a feed, paginate, and rank. Non-functional: the feed should load in a few hundred milliseconds, the system should be highly available, and a new post should appear in followers' feeds within a few seconds.

Assume 500 million daily users, 100 million posts per day and an average of 200 followers per user. That is 20 billion feed entries per day if every post is fanned out to every follower. The write amplification is the central constraint.

## Fan-out on write

When a user publishes a post, the system writes the post id into the feed of every follower. The feed is a precomputed list, often stored in Redis as a sorted set or a list, keyed by user id. Reading the feed is a range query over the list: fast and predictable.

The advantage is read speed. The disadvantage is write amplification: a user with a million followers creates a million writes per post. It also makes the system sensitive to the follow graph: when someone follows a new account, their feed must be backfilled with recent posts, which is another expensive operation.

Fan-out on write is a good fit when the follower distribution is moderate and reads dominate. It is a poor fit for celebrities.

## Fan-out on read

The alternative is to compute the feed when the user opens the app. Read the list of followed users, fetch their recent posts, merge and rank. The write path is cheap: a post is stored once. The read path is expensive: it touches many users and must merge and sort their posts on every request.

For a user who follows 500 accounts, the read path might fetch thousands of candidate posts. Caching helps, but the cache is per user and must be invalidated when any followed account posts. The read amplification replaces the write amplification.

## The hybrid

Most large systems use a hybrid. Normal users get fan-out on write: their posts are pushed to their followers' feeds. Celebrities, defined by a follower threshold, are not fanned out; their posts are fetched at read time and merged into the feed. The threshold is a tuning parameter, not a law.

The read path becomes: fetch the precomputed feed, fetch recent posts from the celebrity accounts the user follows, merge, rank and paginate. The merge is bounded by the number of celebrities a user follows, which is usually small. The write path stays manageable because the heavy hitters are excluded.

## Storage

The post itself lives in a durable store: a relational database or a wide-column store, keyed by post id, with the author, text, media and timestamp. The feed lives in a fast store: Redis sorted sets keyed by user, scored by time or rank. The follow graph lives in a graph store or a table with an index on `follower_id`.

The feed entry is small: a post id and a score. The post content is fetched separately, often in a batch, and cached. This keeps the feed compact and lets the post store scale independently.

## Ranking

A chronological feed is the simplest and the easiest to reason about. A ranked feed uses signals: recency, affinity, engagement, and maybe diversity. The ranking model can run at write time (store a score in the feed) or at read time (score the candidates).

Ranking at write time is cheap for reads but hard to update when the model changes. Ranking at read time is flexible but adds latency. A common compromise is to store a time-based score and apply a light re-ranking on the read path over the first few hundred candidates. The heavy model runs offline and updates the scores periodically.

## Pagination

Offset pagination is a bad fit for a feed because new posts shift the offsets and users see duplicates or miss items. Cursor pagination uses the last seen score or id as the cursor: `where score < cursor order by score desc limit 20`. The cursor is stable even when new posts arrive.

The cursor must encode enough information to resume: the score and the post id, to break ties. It should be opaque to the client and signed if it contains sensitive data. The feed store returns the next cursor, and the client sends it back.

## Cache and rebuild

The precomputed feed is a cache. It can be rebuilt from the follow graph and the post store, at the cost of time. The system should tolerate a missing feed: if the cache is empty, fall back to fan-out on read for that user and repopulate asynchronously.

A common failure is a feed that is stale after a follow or unfollow. The simplest fix is to invalidate the feed and let it rebuild. A more sophisticated system backfills the new follow with recent posts and removes the unfollowed account's posts. The product decides how fresh the change must be.

## Failure modes

If the feed store is down, the read path can fall back to the database, slower but correct. If the post store is down, the feed has ids but no content; the client shows placeholders or an error. If the ranking service is down, the feed falls back to chronological order. Each dependency needs a degradation strategy, and the product needs to know what the user sees.

## What the interviewer wants

They want to hear the trade-off, not a technology list. Why fan-out on write for most users, why a hybrid for celebrities, how the cursor works, what happens when Redis dies, how the follow graph is stored. A clear answer names the constraint and the consequence. A diagram is useful, but the reasoning is the signal.

## Conclusion

A news feed is a read-heavy system with a write-amplification problem. Fan-out on write makes reads fast and writes expensive; fan-out on read does the opposite; the hybrid keeps both bounded. Store posts durably, keep the feed compact, paginate with a cursor, rank with a model that can degrade, and design the fallbacks. The rest is tuning.
