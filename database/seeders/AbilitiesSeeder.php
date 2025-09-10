<?php // v0.3.52

namespace Database\Seeders;

use Faravel\Support\Facades\DB;

class AbilitiesSeeder
{
    public function run(): void
    {
        // Если в БД есть FK, на MySQL удобнее временно отключить проверки
        @DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('TRUNCATE TABLE `abilities`');
        @DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $now = time();

        $rows = [
            // ===== Базовые
            ['id'=>1,'name'=>'site.view','label'=>'View site','description'=>'Публичные страницы','min_role'=>0],
            ['id'=>2,'name'=>'site.search','label'=>'Search','description'=>'Поиск по сайту','min_role'=>0],
            ['id'=>3,'name'=>'auth.login','label'=>'Login','description'=>'Доступ к форме входа','min_role'=>0],
            ['id'=>4,'name'=>'auth.register','label'=>'Register','description'=>'Доступ к форме регистрации','min_role'=>0],
            ['id'=>5,'name'=>'locale.switch','label'=>'Switch locale','description'=>'Смена языка интерфейса','min_role'=>1],

            // ===== Профиль
            ['id'=>6,'name'=>'profile.view.self','label'=>'View own profile','description'=>'Просмотр собственного профиля','min_role'=>1],
            ['id'=>7,'name'=>'profile.update.self','label'=>'Update own profile','description'=>'Редактирование собственного профиля','min_role'=>1],
            ['id'=>8,'name'=>'profile.view.any','label'=>'View any profile','description'=>'Просмотр профиля любого пользователя','min_role'=>3],
            ['id'=>9,'name'=>'profile.update.any','label'=>'Update any profile','description'=>'Редактирование профиля любого пользователя','min_role'=>6],

            // ===== Пользователи (админ/мод)
            ['id'=>10,'name'=>'user.view.list','label'=>'View users list','description'=>'Просмотр списка пользователей в админке','min_role'=>6],
            ['id'=>11,'name'=>'user.view.any','label'=>'View any user','description'=>'Просмотр карточек пользователей','min_role'=>6],
            ['id'=>12,'name'=>'user.edit.any','label'=>'Edit any user','description'=>'Редактирование полей пользователя','min_role'=>6],
            ['id'=>13,'name'=>'user.password.reset.force','label'=>'Force password reset','description'=>'Принудительный сброс пароля','min_role'=>6],
            ['id'=>14,'name'=>'user.role.assign','label'=>'Assign role','description'=>'Назначение ролей пользователям','min_role'=>6],
            ['id'=>15,'name'=>'user.delete.any','label'=>'Delete any user','description'=>'Удаление учётной записи','min_role'=>7],

            // ===== Форум: категории
            ['id'=>16,'name'=>'forum.category.view','label'=>'View categories','description'=>'Просмотр категорий форума','min_role'=>0],
            ['id'=>17,'name'=>'admin.categories.manage','label'=>'Manage categories','description'=>'Управление категориями (создание/правка/порядок)','min_role'=>6],

            // ===== Форум: темы
            ['id'=>18,'name'=>'forum.topic.view','label'=>'View topics','description'=>'Просмотр тем и сообщений','min_role'=>0],
            ['id'=>19,'name'=>'forum.topic.create','label'=>'Create topic','description'=>'Создание новой темы','min_role'=>1],
            ['id'=>20,'name'=>'forum.topic.edit.own','label'=>'Edit own topic','description'=>'Правка своей темы','min_role'=>1],
            ['id'=>21,'name'=>'forum.topic.edit.any','label'=>'Edit any topic','description'=>'Правка чужих тем','min_role'=>3],
            ['id'=>22,'name'=>'forum.topic.delete.own.soft','label'=>'Delete own topic (soft)','description'=>'Мягкое удаление своей темы','min_role'=>1],
            ['id'=>23,'name'=>'forum.topic.delete.any.soft','label'=>'Delete any topic (soft)','description'=>'Мягкое удаление чужих тем','min_role'=>3],
            ['id'=>24,'name'=>'forum.topic.delete.any.hard','label'=>'Delete any topic (hard)','description'=>'Полное удаление темы','min_role'=>6],
            ['id'=>25,'name'=>'forum.topic.restore.any','label'=>'Restore any topic','description'=>'Восстановление мягко удалённых тем','min_role'=>3],
            ['id'=>26,'name'=>'forum.topic.close','label'=>'Close topic','description'=>'Закрыть тему','min_role'=>3],
            ['id'=>27,'name'=>'forum.topic.open','label'=>'Open topic','description'=>'Открыть тему','min_role'=>3],
            ['id'=>28,'name'=>'forum.topic.pin','label'=>'Pin topic','description'=>'Закрепить тему','min_role'=>3],
            ['id'=>29,'name'=>'forum.topic.unpin','label'=>'Unpin topic','description'=>'Открепить тему','min_role'=>3],
            ['id'=>30,'name'=>'forum.topic.move.any','label'=>'Move topic','description'=>'Перенос темы между категориями','min_role'=>3],
            ['id'=>31,'name'=>'forum.topic.merge.any','label'=>'Merge topics','description'=>'Слияние тем','min_role'=>3],
            ['id'=>32,'name'=>'forum.topic.split.any','label'=>'Split topic','description'=>'Разделение темы','min_role'=>3],
            ['id'=>33,'name'=>'forum.topic.rename.own','label'=>'Rename own topic','description'=>'Переименование своей темы','min_role'=>1],
            ['id'=>34,'name'=>'forum.topic.rename.any','label'=>'Rename any topic','description'=>'Переименование чужих тем','min_role'=>3],
            ['id'=>35,'name'=>'forum.topic.tag.manage','label'=>'Manage topic tags','description'=>'Управление тегами темы','min_role'=>3],

            // ===== Форум: сообщения
            ['id'=>36,'name'=>'forum.post.create','label'=>'Create post','description'=>'Создание сообщения в теме','min_role'=>1],
            ['id'=>37,'name'=>'forum.post.edit.own','label'=>'Edit own post','description'=>'Правка своего сообщения','min_role'=>1],
            ['id'=>38,'name'=>'forum.post.edit.any','label'=>'Edit any post','description'=>'Правка чужих сообщений','min_role'=>3],
            ['id'=>39,'name'=>'forum.post.delete.own.soft','label'=>'Delete own post (soft)','description'=>'Мягкое удаление своего сообщения','min_role'=>1],
            ['id'=>40,'name'=>'forum.post.delete.any.soft','label'=>'Delete any post (soft)','description'=>'Мягкое удаление чужих сообщений','min_role'=>3],
            ['id'=>41,'name'=>'forum.post.delete.any.hard','label'=>'Delete any post (hard)','description'=>'Полное удаление чужих сообщений','min_role'=>6],
            ['id'=>42,'name'=>'forum.post.restore.any','label'=>'Restore any post','description'=>'Восстановление мягко удалённых сообщений','min_role'=>3],
            ['id'=>43,'name'=>'forum.post.view.deleted','label'=>'View deleted posts','description'=>'Просмотр удалённых сообщений','min_role'=>3],
            ['id'=>44,'name'=>'forum.post.reaction.add','label'=>'Add reaction','description'=>'Добавление реакции к сообщению','min_role'=>1],
            ['id'=>45,'name'=>'forum.post.reaction.remove.own','label'=>'Remove own reaction','description'=>'Удаление своей реакции','min_role'=>1],

            // ===== Вложения
            ['id'=>46,'name'=>'forum.attachment.upload','label'=>'Upload attachment','description'=>'Загрузка вложений','min_role'=>1],
            ['id'=>47,'name'=>'forum.attachment.delete.own','label'=>'Delete own attachment','description'=>'Удаление своих вложений','min_role'=>1],
            ['id'=>48,'name'=>'forum.attachment.delete.any','label'=>'Delete any attachment','description'=>'Удаление чужих вложений','min_role'=>3],

            // ===== Теги
            ['id'=>49,'name'=>'forum.tag.assign','label'=>'Assign tags','description'=>'Назначение тегов','min_role'=>1],
            ['id'=>50,'name'=>'forum.tag.create','label'=>'Create tag','description'=>'Создание тегов','min_role'=>3],
            ['id'=>51,'name'=>'forum.tag.delete','label'=>'Delete tag','description'=>'Удаление тегов','min_role'=>3],

            // ===== Жалобы
            ['id'=>52,'name'=>'report.create','label'=>'Create report','description'=>'Пожаловаться на контент','min_role'=>1],
            ['id'=>53,'name'=>'report.view','label'=>'View reports','description'=>'Просмотр жалоб','min_role'=>3],
            ['id'=>54,'name'=>'report.resolve','label'=>'Resolve reports','description'=>'Обработка/закрытие жалоб','min_role'=>3],

            // ===== Предмодерация
            ['id'=>55,'name'=>'moderation.queue.view','label'=>'View moderation queue','description'=>'Просмотр очереди на модерации','min_role'=>3],
            ['id'=>56,'name'=>'moderation.post.approve','label'=>'Approve post','description'=>'Апрув/реджект постов','min_role'=>3],
            ['id'=>57,'name'=>'moderation.topic.approve','label'=>'Approve topic','description'=>'Апрув/реджект тем','min_role'=>3],

            // ===== Санкции
            ['id'=>58,'name'=>'moderation.user.note.add','label'=>'Add user note','description'=>'Модераторская заметка в профиле','min_role'=>3],
            ['id'=>59,'name'=>'moderation.user.warn','label'=>'Warn user','description'=>'Вынесение предупреждения','min_role'=>3],
            ['id'=>60,'name'=>'moderation.user.mute','label'=>'Mute user','description'=>'Отключение возможности писать (мут)','min_role'=>3],
            ['id'=>61,'name'=>'moderation.user.suspend','label'=>'Suspend user','description'=>'Временная блокировка учётки','min_role'=>6],
            ['id'=>62,'name'=>'moderation.user.ban','label'=>'Ban user','description'=>'Перманентная блокировка','min_role'=>6],
            ['id'=>63,'name'=>'moderation.user.unban','label'=>'Unban user','description'=>'Снятие блокировки','min_role'=>6],
            ['id'=>64,'name'=>'moderation.user.shadowban','label'=>'Shadowban user','description'=>'Теневой бан','min_role'=>6],

            // ===== Админка
            ['id'=>65,'name'=>'admin.access','label'=>'Admin panel access','description'=>'Доступ в админ-панель','min_role'=>6],
            ['id'=>66,'name'=>'admin.settings.manage','label'=>'Manage settings','description'=>'Управление системными настройками','min_role'=>6],
            ['id'=>67,'name'=>'admin.users.manage','label'=>'Manage users','description'=>'Управление пользователями','min_role'=>6],
            ['id'=>68,'name'=>'admin.roles.manage','label'=>'Manage roles','description'=>'Управление ролями','min_role'=>6],
            ['id'=>69,'name'=>'admin.abilities.manage','label'=>'Manage abilities','description'=>'Управление способностями','min_role'=>6],
            ['id'=>70,'name'=>'admin.audit.view','label'=>'View audit log','description'=>'Просмотр журнала действий','min_role'=>6],
            ['id'=>71,'name'=>'admin.cache.flush','label'=>'Flush cache','description'=>'Сброс кеша приложения','min_role'=>6],
            ['id'=>72,'name'=>'admin.backup.run','label'=>'Run backup','description'=>'Запуск резервного копирования','min_role'=>6],
            ['id'=>73,'name'=>'admin.maintenance.toggle','label'=>'Toggle maintenance','description'=>'Режим обслуживания: вкл/выкл','min_role'=>6],
            ['id'=>74,'name'=>'admin.impersonate','label'=>'Impersonate user','description'=>'Вход от имени пользователя','min_role'=>7],
            ['id'=>76,'name'=>'admin.perks.manage','label'=>'Manage perks','description'=>'Управление перками (Perks)','min_role'=>6],

            // ===== Системные
            ['id'=>75,'name'=>'system.god_mode','label'=>'God Mode','description'=>'Режим владельца (всегда разрешено)','min_role'=>7],
        ];

        foreach ($rows as $r) {
            DB::insert(
                "INSERT INTO `abilities` (`id`,`name`,`label`,`description`,`min_role`,`created_at`,`updated_at`)
                 VALUES (?,?,?,?,?,?,?)",
                [$r['id'], $r['name'], $r['label'], $r['description'], $r['min_role'], $now, $now]
            );
        }
    }
}
