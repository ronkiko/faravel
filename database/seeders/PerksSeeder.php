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
/* possible perks list (can be extended)
INSERT INTO perks(`key`,`label`,`description`,`min_group_id`) VALUES
-- 1 lurker
('perk.profile.avatar.basic','Basic avatar','Загрузка аватара до 256×256, 200 KB',1),
('perk.reaction.like','Like reactions','Можно ставить лайки',1),

-- 2 junior
('perk.profile.signature.use','Use signature','Подпись под сообщениями (до 120 симв.)',2),
('perk.attach.limit.s','Small attachments','Вложения до 500 KB',2),
('perk.topic.create.poll','Create polls','Создание простых опросов',2),

-- 3 member
('perk.edit.window.m','Extended edit window','Редактирование своих постов 30 мин',3),
('perk.profile.links','Profile links','Ссылки в профиле и подписи',3),
('perk.bookmark.post','Post bookmarks','Закладки на посты',3),

-- 4 advanced
('perk.title.custom','Custom title','Пользовательский титул под ником',4),
('perk.attach.limit.m','Medium attachments','Вложения до 2 MB',4),
('perk.topic.tag','Tag topics','Добавление тегов к своим темам',4),

-- 5 master
('perk.signature.rich','Rich signature','Подпись до 240 симв., базовое форматирование',5),
('perk.topic.image.cover','Topic cover image','Обложка темы (картинка)',5),
('perk.reaction.set.ext','Extended reactions','Расширенный набор реакций',5),

-- 6 senior
('perk.username.change.y1','Username change 1/y','Смена ника раз в год',6),
('perk.post.embed.media','Embed media','Встраивание видео/аудио',6),
('perk.collection.public','Public collections','Публичные подборки ссылок/тем',6),

-- 7 veteran
('perk.topic.pin.self','Pin own topic (self)','Закреплять СВОИ темы в списке для себя',7),
('perk.dm.group.start','Start group DM','Старт групповых личных бесед',7),
('perk.attach.limit.l','Large attachments','Вложения до 8 MB',7),

-- 8 patron
('perk.badge.patron','Patron badge','Отметка «патрoн/спонсор»',8),
('perk.theme.profile','Profile theme','Цветовая тема карточки профиля',8),
('perk.beta.access','Beta features','Ранний доступ к бета-функциям',8),

-- 9 star
('perk.profile.vanity','Vanity URL','Красивая ссылка на профиль',9),
('perk.emoji.custom.use','Use custom emoji','Использование кастомных эмодзи',9),
('perk.post.highlight.self','Highlight own posts','Ненавязчивая подсветка своих постов',9),

-- 10 elite
('perk.topic.series','Topic series','Серии тем с оглавлением',10),
('perk.guide.featured.submit','Submit featured guides','Подача гайдов на витрину',10),
('perk.reaction.quota.boost','Reaction quota boost','Увеличенная квота реакций/сутки',10),

-- 11 legend
('perk.badge.legend','Legend badge','Золотой бейдж «Legend»',11),
('perk.profile.frame.legend','Legend frame','Золотая рамка аватара',11),
('perk.post.priority.showcase','Priority showcase','Приоритет в витрине избранных постов',11);
*/