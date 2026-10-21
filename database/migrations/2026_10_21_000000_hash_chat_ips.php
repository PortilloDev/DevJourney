<?php

declare(strict_types=1);

use App\Services\Rag\IpHasher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @param  list<string>  $tables
     */
    public function up(): void
    {
        $hasher = new IpHasher;

        foreach (['ai_usage', 'ai_conversations'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $hasher): void {
                foreach ($rows as $row) {
                    $value = $row->ip_address;

                    if ($value === null || $this->looksAlreadyDerived($value)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['ip_address' => $hasher->hash((string) $value)]);
                }
            });
        }
    }

    public function down(): void
    {
        // One-way derivation: not reversible. No-op.
    }

    private function looksAlreadyDerived(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/i', $value);
    }
};
