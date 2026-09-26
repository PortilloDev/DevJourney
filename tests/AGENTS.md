# tests/ — PHPUnit

Scope-specific rules for `tests/`. Root `AGENTS.md` wins on anything not covered here.

- Every test that touches the DB uses `RefreshDatabase`. The suite runs on
  **SQLite `:memory:`** (forced in `tests/TestCase.php`), so avoid MySQL-only SQL
  and driver-specific schema calls.
- **Admin/panel tests**: the panel is allow-listed by email, so do
  `config(['app.filament_admin_email' => 'admin@devjourney.test'])` and then
  `actingAs(User::factory()->create(['email' => 'admin@devjourney.test']))` — a
  random factory email is forbidden (403/404).
- **RAG is disabled in tests** via `phpunit.xml` (`QDRANT_ENABLED=false`, empty
  `LLM_API_KEY`). To exercise it, bind fakes explicitly — see `RagSystemTest`
  (`InMemoryVectorStore` + fake `EmbeddingService`/`LlmClient`).
- Feature tests hit real routes; assert status + rendered content. For streamed
  responses use `TestResponse::streamedContent()` (not `getContent()`).
- Run a subset with
  `docker compose -f docker-compose.dev.yml exec app php artisan test --filter=SomeTest`.
