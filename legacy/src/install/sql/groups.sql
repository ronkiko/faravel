DROP TABLE IF EXISTS `groups`;

CREATE TABLE groups (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    reputation INT UNSIGNED DEFAULT NULL  -- NULL означает, что группа недостижима автоматически
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO groups (id, name, description, reputation) VALUES
(0, 'guest', 'Not logged in, anonymous visitor', NULL),
(1, 'lurker', 'Registered but inactive', 0),
(2, 'junior', 'New user, just beginning to engage', 1),
(3, 'member', 'Active participant, met baseline engagement level', 10),
(4, 'advanced', 'Consistently involved, increasing engagement', 100),
(5, 'master', 'Skilled and dedicated member deeply engaged in forum activity', 250),
(6, 'senior', 'Respected member with a history of high engagement', 500),
(7, 'veteran', 'Long-time, highly engaged and influential member', 1000),
(8, 'patron', 'Supporter or sponsor of the forum', NULL),
(9, 'star', 'Widely recognized for charisma or prominent presence', 2000),
(10, 'elite', 'Part of the top inner circle of highly engaged members', 5000),
(11, 'legend', 'Iconic figure known for extraordinary and sustained engagement', 10000);
