<?php // v0.3.4

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        // 1) Переименовать столбец language -> language_id и зафиксировать тип/дефолт
        DB::connection()->exec("
            ALTER TABLE users
            CHANGE COLUMN `language` `language_id` TINYINT UNSIGNED NOT NULL DEFAULT 1
        ");

        // 2) Внешний ключ на справочник языков
        DB::connection()->exec("
            ALTER TABLE users
            ADD CONSTRAINT fk_user_language
                FOREIGN KEY (`language_id`) REFERENCES languages(`id`)
                ON UPDATE RESTRICT ON DELETE RESTRICT
        ");
    }

    public function down()
    {
        // Откат: снять FK и вернуть имя столбца
        DB::connection()->exec("ALTER TABLE users DROP FOREIGN KEY fk_user_language");

        DB::connection()->exec("
            ALTER TABLE users
            CHANGE COLUMN `language_id` `language` TINYINT UNSIGNED NOT NULL DEFAULT 1
        ");
    }
};
