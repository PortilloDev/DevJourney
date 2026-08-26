<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // The encrypted payload for an IP is longer than 64 chars, so widen it.
        // SQLite does not enforce column lengths and has no MODIFY clause.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE visits MODIFY ip_address TEXT NULL');
        }

        // Re-encrypt any personal data that was stored in plaintext before the
        // encrypted casts were added. Laravel's encrypted payloads always start
        // with the base64 encoding of `{"iv":`, i.e. "eyJpdiI6", so we only
        // touch values that are not already encrypted.
        DB::table('visits')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $updates = [];

                foreach (['ip_address', 'user_agent', 'referer'] as $column) {
                    $value = $row->{$column};

                    if ($value !== null && ! Str::startsWith($value, 'eyJpdiI6')) {
                        $updates[$column] = Crypt::encryptString($value);
                    }
                }

                if ($updates !== []) {
                    DB::table('visits')->where('id', $row->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE visits MODIFY ip_address VARCHAR(64) NULL');
        }
        // Encrypted personal data is intentionally not decrypted back on rollback.
    }
};
