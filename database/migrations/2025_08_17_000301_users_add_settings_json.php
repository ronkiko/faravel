<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up(): void
    {
        // Попытаться JSON; если СУБД не поддерживает, откатимся на TEXT.
        try {
            DB::statement("ALTER TABLE `users` ADD COLUMN `settings` JSON NULL AFTER `signature`");
        } catch (\Throwable $e) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `settings` TEXT NULL AFTER `signature`");
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE `users` DROP COLUMN `settings`");
        } catch (\Throwable $e) {
            // no-op
        }
    }
};
