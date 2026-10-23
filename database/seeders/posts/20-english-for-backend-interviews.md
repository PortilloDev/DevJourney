---
title: English for backend interviews: talking about trade-offs
category: Career
level: B1
tags: english, interviews
excerpt: The phrases that make a technical answer sound senior — and a practice loop for turning passive vocabulary into fluent speech.
featured: false
---
## The interview is a communication test

A senior backend interview is not only a test of knowledge. It is a test of whether you can explain a decision, defend it, and change your mind when the evidence changes. In a second language, that is harder. The vocabulary is there, but the sentences take longer to assemble, and the pressure makes the simple words disappear.

The good news is that interview English is narrow. You do not need poetry. You need a small set of structures that let you compare options, describe constraints, admit uncertainty and walk through a design. I practise those structures the same way I practise algorithms: deliberately, out loud, with feedback.

## The vocabulary of trade-offs

Most senior answers are comparisons. The useful phrases are the ones that frame a comparison:

- "The trade-off is between latency and consistency."
- "This approach is simpler, but it does not scale horizontally."
- "The downside is operational complexity."
- "We can accept eventual consistency here because..."
- "The bottleneck is the write path, not the read path."
- "That gives us a bounded blast radius."
- "It is a reasonable default until the traffic justifies something else."

Notice the pattern: option, consequence, condition. "Redis is faster, but it adds a cache invalidation problem, which is acceptable for a blog page but not for a balance." That sentence is more valuable than a list of technologies.

I keep a notebook of these patterns and use them in mock interviews until they are automatic. The goal is not to sound clever; it is to make the reasoning visible while the grammar is still catching up.

## Structuring an answer

When asked to design something, I use a simple structure:

1. **Clarify the requirements.** Functional and non-functional. Ask about scale, latency, consistency and budget.
2. **State the constraints.** What is fixed, what is negotiable, what is out of scope.
3. **Propose two or three options.** Name the trade-off of each.
4. **Choose one and justify it.** Explain why the constraints favour it.
5. **Describe the failure modes.** What happens when a dependency dies.
6. **Summarise.** One or two sentences that repeat the decision.

The structure buys time and shows seniority. It also prevents the common failure of jumping into a diagram before understanding the problem. An interviewer would rather hear a clarifying question than a confident answer to the wrong question.

## Clarifying questions

The phrases I use most:

- "Before I design, can I confirm the scale we are targeting?"
- "Is strong consistency a requirement here, or is eventual consistency acceptable?"
- "What is the read-to-write ratio?"
- "Are we optimising for latency, cost, or operational simplicity?"
- "Is this a greenfield system or an existing one with constraints?"

These questions are not a trick. They change the design. A feed with a hundred users and a feed with a hundred million users have different answers. Asking shows that you know the difference.

## Describing a system you built

For experience questions, the STAR structure works in any language: situation, task, action, result. I keep each part short and concrete. "We had a checkout that timed out under load. My task was to reduce the p95 latency. I introduced a queue for the email step and added an index on the order lookup. The p95 dropped from 1.8 seconds to 300 milliseconds, and the timeout rate fell to zero."

The result should be measurable. "It was faster" is weak; "it dropped from 1.8 seconds to 300 milliseconds" is strong. If you do not remember the exact number, give a range and say it is approximate. Honesty is more convincing than a suspiciously precise figure.

## Admitting uncertainty

Senior engineers say "I do not know" and then say how they would find out. In an interview, that is a strength, not a weakness. Useful phrases:

- "I have not used that in production, but my understanding is..."
- "I would check the query plan before choosing an index."
- "I would start with the simplest option and measure."
- "That is a good point; I would reconsider the cache if the write rate were higher."

The last one is important. An interview is a conversation. Changing your mind when the interviewer adds a constraint is exactly what a senior engineer does. Do not defend a decision that no longer fits the requirements.

## Common mistakes

The first is translating word by word from your native language. The sentence becomes long and the verb lands at the end. Short sentences are better. Subject, verb, object. Then add the condition.

The second is overusing filler. "Basically", "actually", "you know" add noise. A short pause is better than a filler. The interviewer is not measuring speed; they are measuring clarity.

The third is apologising for your English. Do not. You are communicating technical ideas in a second language; that is the job. If a word does not come, describe it: "the component that receives the request and decides where it goes" is a fine way to say "router".

## A practice loop

I practise three times a week, twenty minutes each. Ten minutes explaining a system out loud, recorded on my phone. Five minutes listening and noting the sentences that were slow. Five minutes repeating those sentences with a better structure.

I also keep a list of ten questions and answer one per day: "Design a rate limiter", "Tell me about a production incident", "Why did you choose that database?" The questions repeat, but the answers get smoother. Fluency is repetition with feedback, not a new textbook.

## Conclusion

Interview English is a small, practical vocabulary: compare options, state constraints, admit uncertainty, describe results. Use a structure, ask clarifying questions, and practise out loud. The grammar will improve with reps, but the clarity can improve today. A simple sentence that explains a trade-off beats a complex sentence that hides it.
