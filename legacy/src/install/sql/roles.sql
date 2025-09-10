DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` TINYINT NOT NULL, -- теперь SIGNED
  `name` VARCHAR(50) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `description` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id, name, label, description) VALUES
(-1, 'banned', 'Banned', 'Restricted from all access due to violations'),
(0,  'guest', 'Guest', 'Unregistered or not logged in'),
(1,  'user', 'User', 'Regular registered user'),
(2,  'support', 'Support', 'Assists moderators and administration'),
(3,  'moderator', 'Moderator', 'Moderates assigned categories or specific sections'),
(4,  'super', 'Super Moderator', 'Has moderation access to the entire forum'),
(5,  'developer', 'Developer', 'Has technical permissions and access'),
(6,  'administrator', 'Administrator', 'Full access to forum management'),
(7,  'owner', 'Owner', 'Ultimate authority and forum ownership');
