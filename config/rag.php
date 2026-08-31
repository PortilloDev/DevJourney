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
        'enabled' => (bool) env('QDRANT_ENABLED', true),
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
        'min_similarity' => (float) env('RAG_MIN_SIMILARITY', 0.35),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public chat limits (per visitor)
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'requests_per_day' => (int) env('RAG_REQUESTS_PER_DAY', 20),
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
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 900),
        'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 120),
    ],
];
