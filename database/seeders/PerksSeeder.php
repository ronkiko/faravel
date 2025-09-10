<?php // v0.1.0

namespace Database\Seeders;

use Faravel\Support\Facades\DB;

class PerksSeeder
{
    public function run(): void
    {
        DB::statement("TRUNCATE TABLE `perks`");
        $now = time();

        DB::insert(
            "INSERT INTO `perks` (`key`,`label`,`description`,`min_group_id`,`created_at`,`updated_at`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                'perk.profile.signature.use',
                'Use signature',
                'Возможность добавлять подпись к сообщениям',
                2, $now, $now
            ]
        );
    }
}
