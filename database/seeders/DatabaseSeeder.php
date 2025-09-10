<?php // v0.3.6

namespace Database\Seeders;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Группы (id, name, description, reputation) ----------
        $groups = [
            [0,  'guest',    'Not logged in, anonymous visitor',                         null],
            [1,  'lurker',   'Registered but inactive',                                   0],
            [2,  'junior',   'New user, just beginning to engage',                        1],
            [3,  'member',   'Active participant, met baseline engagement level',        10],
            [4,  'advanced', 'Consistently involved, increasing engagement',            100],
            [5,  'master',   'Skilled and dedicated member deeply engaged in activity', 250],
            [6,  'senior',   'Respected member with a history of high engagement',     500],
            [7,  'veteran',  'Long-time, highly engaged and influential member',      1000],
            [8,  'patron',   'Supporter or sponsor of the forum',                        null],
            [9,  'star',     'Widely recognized for charisma or prominent presence',   2000],
            [10, 'elite',    'Top inner circle of highly engaged members',             5000],
            [11, 'legend',   'Iconic figure known for extraordinary engagement',      10000],
        ];

        foreach ($groups as [$id, $name, $desc, $rep]) {
            $this->db->statement(
                "INSERT INTO groups (id, name, description, reputation)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   name = VALUES(name),
                   description = VALUES(description),
                   reputation = VALUES(reputation)",
                [$id, $name, $desc, $rep]
            );
        }

        // ---------- Роли (id, name, label, description) ----------
        $roles = [
            [-1, 'banned',       'Banned',           'Restricted from all access due to violations'],
            [0,  'guest',        'Guest',            'Unregistered or not logged in'],
            [1,  'user',         'User',             'Regular registered user'],
            [2,  'support',      'Support',          'Assists moderators and administration'],
            [3,  'moderator',    'Moderator',        'Moderates assigned categories/sections'],
            [4,  'super',        'Super Moderator',  'Has moderation access to the entire forum'],
            [5,  'developer',    'Developer',        'Has technical permissions and access'],
            [6,  'administrator','Administrator',    'Full access to forum management'],
            [7,  'owner',        'Owner',            'Ultimate authority and forum ownership'],
        ];

        foreach ($roles as [$id, $name, $label, $desc]) {
            $this->db->statement(
                "INSERT INTO roles (id, name, label, description)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   name = VALUES(name),
                   label = VALUES(label),
                   description = VALUES(description)",
                [$id, $name, $label, $desc]
            );
        }

        // ---------- Языки (id, code, name, is_active, sort) ----------
        // Соответствует миграции: users.language_id → languages.id (FK)
        $this->db->statement(
            "INSERT INTO languages (id, code, name, is_active, sort) VALUES
                (1, 'en', 'English', 1, 10),
                (2, 'ru', 'Русский', 1, 20)
             ON DUPLICATE KEY UPDATE
                code=VALUES(code),
                name=VALUES(name),
                is_active=VALUES(is_active),
                sort=VALUES(sort)"
        );

        // ---------- Админ-пользователь (idempotent upsert) ----------
        $adminUuid = '1bd5d75c-655c-427f-a424-339c8840f799';
        $hash      = '$2y$10$TB8Tdqym2B5aoIPIfLOTt.yUk/6133qL.tHVOsyJoOs4qh1AISEnS'; // ваш bcrypt
        $roleId    = 6;   // administrator
        $groupId   = 1;   // lurker (или поменяйте на нужную стартовую группу)

        $exists = (int)$this->db->scalar("SELECT COUNT(*) FROM users WHERE id = ?", [$adminUuid]);

        if ($exists === 0) {
            $this->db->insert(
                "INSERT INTO users
                   (id, username, password, registered, reputation, group_id, last_visit, last_post, role_id, language_id, title, style, signature)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $adminUuid, 'admin', $hash,
                    time(), // registered
                    0,      // reputation
                    $groupId,
                    null,   // last_visit
                    null,   // last_post
                    $roleId,
                    1,      // language_id (EN)
                    null,   // title
                    0,      // style
                    null,   // signature
                ]
            );
        } else {
            $this->db->statement(
                "UPDATE users SET password=?, role_id=?, group_id=? WHERE id=?",
                [$hash, $roleId, $groupId, $adminUuid]
            );
        }

        // ---------- Settings (idempotent; НЕ перетираем вручную выставленные) ----------
        try {
            $this->db->scalar("SELECT 1 FROM settings LIMIT 1");

            $now = date('Y-m-d H:i:s');
            $settings = [
                ['throttle.window.sec',   '60'],
                ['throttle.get.max',      '120'],
                ['throttle.post.max',     '15'],
                ['throttle.session.max',  '300'],
                ['throttle.exempt.paths', ''], // CSV, например: "/__cfg,/__db_ping"
            ];

            foreach ($settings as [$key, $value]) {
                $this->db->statement(
                    "INSERT INTO settings (`key`, `value`, `created_at`, `updated_at`)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE `value` = `value`",
                    [$key, $value, $now, $now]
                );
            }
        } catch (\Throwable $e) {
            echo "[seed] settings table missing; run migrations first\n";
        }

        echo "[seed] done\n";
    }
}
