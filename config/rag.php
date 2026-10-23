<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Embeddings are produced by an OpenAI-compatible API; chat completions by
    | an OpenAI-compatible endpoint (OpenAI, DeepSeek, Ollama, …). `chat_model`
    | and the base URLs are usually provider-specific.
    |
    */
    'provider' => env('LLM_PROVIDER', 'openai'),
    'base_url' => env('LLM_BASE_URL', 'https://api.openai.com/v1'),
    'api_key' => env('LLM_API_KEY'),
    'chat_model' => env('LLM_CHAT_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    */
    'embeddings' => [
        'model' => env('EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1536),
        'base_url' => env('EMBEDDING_BASE_URL', env('LLM_BASE_URL', 'https://api.openai.com/v1')),
        'api_key' => env('EMBEDDING_API_KEY', env('LLM_API_KEY')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector store
    |--------------------------------------------------------------------------
    */
    'qdrant' => [
        'enabled' => filter_var(env('QDRANT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'host' => env('QDRANT_HOST', 'http://qdrant:6333'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'content'),
        'distance' => env('QDRANT_DISTANCE', 'Cosine'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */
    'retrieval' => [
        'top_k' => (int) env('RAG_TOP_K', 5),
        'min_similarity' => (float) env('RAG_MIN_SIMILARITY', 0.30),
        'related_min_similarity' => (float) env('RAG_RELATED_MIN_SIMILARITY', 0.20),
        'confident_similarity' => (float) env('RAG_CONFIDENT_SIMILARITY', 0.65),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider timeouts (seconds)
    |--------------------------------------------------------------------------
    |
    | Hard cap on how long a provider call may run. Keep nginx's
    | `fastcgi_read_timeout` and PHP-FPM's `request_terminate_timeout` above these
    | values (see README) so the app, not the web server, decides when to abort.
    |
    */
    'timeouts' => [
        'complete' => (int) env('RAG_TIMEOUT_COMPLETE', 30),
        'stream' => (int) env('RAG_TIMEOUT_STREAM', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider circuit breaker
    |--------------------------------------------------------------------------
    |
    | Consecutive provider failures above `threshold` open the circuit for
    | `cooldown_seconds`, during which chat calls fail fast without invoking the
    | provider. `threshold` is exclusive: the circuit opens when failures exceed it.
    |
    */
    'circuit' => [
        'threshold' => (int) env('RAG_CIRCUIT_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('RAG_CIRCUIT_COOLDOWN', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Answer / embedding cache
    |--------------------------------------------------------------------------
    |
    | Stored answers are reused for a repeated, normalized question on the same
    | post until the post changes or the TTL elapses. Question embeddings are
    | cached separately, keyed by the embedding model. TTLs are in seconds.
    |
    */
    'cache' => [
        'answer_ttl' => (int) env('RAG_CACHE_ANSWER_TTL', 604800),
        'embedding_ttl' => (int) env('RAG_CACHE_EMBEDDING_TTL', 604800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-post chat
    |--------------------------------------------------------------------------
    |
    | Answers are scoped to a single post. `max_answer_tokens` caps the length of
    | the answer (~2-3 tweets). The thresholds decide relevance locally (lexical
    | overlap) before falling back to the embedding provider.
    |
    */
    'post_chat' => [
        'max_answer_tokens' => (int) env('RAG_POST_MAX_ANSWER_TOKENS', 200),
        'related_threshold' => (float) env('RAG_POST_RELATED_THRESHOLD', 0.5),
        'unrelated_threshold' => (float) env('RAG_POST_UNRELATED_THRESHOLD', 0.15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generic chat
    |--------------------------------------------------------------------------
    |
    | Output cap for the generic `ask()`/`askStream()` path, kept separate from
    | the per-post chat cap and from the input token limit.
    |
    */
    'general_chat' => [
        'max_answer_tokens' => (int) env('RAG_GENERAL_MAX_ANSWER_TOKENS', 400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversational memory
    |--------------------------------------------------------------------------
    |
    | A follow-up question is rewritten into a standalone one by `rewrite.model`
    | (falls back to the chat model when empty), capped at `rewrite.max_tokens`.
    | The last `conversation.max_turns` turns are sent as history.
    |
    */
    'rewrite' => [
        'model' => env('RAG_REWRITE_MODEL'),
        'max_tokens' => (int) env('RAG_REWRITE_MAX_TOKENS', 60),
    ],

    'conversation' => [
        'max_turns' => (int) env('RAG_CONVERSATION_MAX_TURNS', 3),
        'max_tokens' => (int) env('RAG_CONVERSATION_MAX_TOKENS', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit logging
    |--------------------------------------------------------------------------
    |
    | When enabled, ai_usage.prompt stores the full messages sent to the model
    | (system with context, history and question). Disable it to keep only the
    | system prompt and question.
    |
    */
    'log_full_prompt' => filter_var(env('RAG_LOG_FULL_PROMPT', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Cost estimation
    |--------------------------------------------------------------------------
    |
    | Price per million tokens in euros, indexed by the chat model, split into
    | `input` and `output`. A model with no entry here records no cost. Used to
    | fill `ai_usage.cost_eur` when a turn is logged. Keep the numbers in sync
    | with the provider's current prices.
    |
    */
    'pricing' => [
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public chat limits (per visitor)
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'requests_per_day' => (int) env('RAG_REQUESTS_PER_DAY', 20),
        'global_requests_per_day' => (int) env('RAG_GLOBAL_REQUESTS_PER_DAY', 500),
        'tokens_per_day' => (int) env('RAG_TOKENS_PER_DAY', 20000),
        'max_input_tokens' => (int) env('RAG_MAX_INPUT_TOKENS', 2000),
        'max_context_chunks' => (int) env('RAG_CONTEXT_CHUNKS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Indexing
    |--------------------------------------------------------------------------
    */
    'indexing' => [
        // Content types that receive embeddings. Keep this list aligned with
        // the retrieval paths: a type no path can reach only costs money.
        'source_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('RAG_INDEXING_SOURCE_TYPES', 'post,document')),
        ))),
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 900),
        'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Answer quality evaluation
    |--------------------------------------------------------------------------
    |
    | Parameters for the `ai:eval` command. `judge_model` scores grounding when
    | `--judge` is passed (defaults to the chat model); `min_score` is the
    | pass-rate floor that turns the command's exit code non-zero.
    |
    */
    'eval' => [
        'judge_model' => env('RAG_EVAL_JUDGE_MODEL', env('LLM_CHAT_MODEL', 'gpt-4o-mini')),
        'min_score' => (float) env('RAG_EVAL_MIN_SCORE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation log retention
    |--------------------------------------------------------------------------
    |
    | Chat conversations (and their turns) older than this many days are pruned
    | by the `ai:prune-conversations` scheduled command.
    |
    */
    'retention_days' => (int) env('RAG_RETENTION_DAYS', 30),
];
