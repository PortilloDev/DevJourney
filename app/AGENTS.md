# app/ — application code

Scope-specific rules for `app/`. Root `AGENTS.md` wins on anything not covered here.

- **Layering**: domain models in `app/Models`, typed enums in `app/Enums`,
  business logic in `app/Services`. Register services/singletons in
  `AppServiceProvider::register()`. Prefer a service over fat controllers/models.
- **Markdown models**: `Post`, `Challenge`, `Project` pre-render `*_md` → `*_html`
  in a `booted()` `saving` hook via `MarkdownRenderer`. Keep both fields in sync.
- **Slugs / tags**: use `App\Models\Concerns\HasSlug` and `Taggable` rather than
  hand-rolling slug or polymorphic-tag logic.
- **Filament**: `form()` and `table()` are `static`; a plain widget must declare
  `protected static string $view`; avoid `Infolists\...\TextEntry::monospace()`
  and other methods that do not exist in Filament 3.3.
- **Public controllers** live in `Http/Controllers/Public` and populate the
  request-scoped `SeoMetaService` — every public page needs its meta.
- **New content models**: wire cache invalidation, audit logging and RAG indexing
  through the model events centralised in `AppServiceProvider::boot()` instead of
  scattering observers.
- **RAG** (`Services/Rag`, `Services/Embedding`, `Services/Llm`,
  `Services/Vector`): the vector store and LLM clients are bound to interfaces in
  `AppServiceProvider`; add behaviour behind the interfaces so it stays testable.
