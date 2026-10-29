-- ===========================================================================
-- Match module schema — for hosting where `php artisan migrate` cannot be run
-- ===========================================================================
--
-- Covers migrations 2024_01_01_000022 through 000026: the teams, team_matches
-- and match_events tables plus the clock, squad and court-swap columns.
--
-- WHY YOU MIGHT NEED THIS
--   Without SSH there is no way to run migrations on Hostinger. If those
--   columns are missing, the clocks read 00:00 and the raid counter sticks at
--   0, because `timer_duration_seconds` and `raid_duration_seconds` come back
--   as NULL and the countdown is computed from them.
--
-- HOW TO RUN IT
--   hPanel -> Databases -> phpMyAdmin -> pick your database -> SQL tab ->
--   paste this file -> Go.
--
-- SAFE TO RUN TWICE
--   The CREATE TABLE statements use IF NOT EXISTS. The ALTER TABLE statements
--   do not (MySQL has no IF NOT EXISTS for columns), so if a column is already
--   there you will see "Duplicate column name '...'". That error is harmless —
--   it means that part was already applied. Keep going.
--
-- CHECK FIRST (optional): run this to see what is already present.
--
--   SELECT TABLE_NAME, COLUMN_NAME
--   FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE()
--     AND TABLE_NAME IN ('teams','team_matches','match_events')
--   ORDER BY TABLE_NAME, ORDINAL_POSITION;
--
-- ===========================================================================


-- ── 000022: teams ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `teams` (
  `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255)     NOT NULL,
  `slug`       VARCHAR(255)     NOT NULL,
  `short_name` VARCHAR(10)      NULL DEFAULT NULL,
  `logo`       VARCHAR(255)     NULL DEFAULT NULL,
  `status`     TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP        NULL DEFAULT NULL,
  `updated_at` TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_slug_unique` (`slug`),
  KEY `teams_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 000023: team_matches ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `team_matches` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_1`        BIGINT UNSIGNED NOT NULL,
  `team_2`        BIGINT UNSIGNED NOT NULL,
  `status`        VARCHAR(20)     NOT NULL DEFAULT 'pending',
  `team_1_score`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `team_2_score`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `scheduled_at`  DATETIME        NULL DEFAULT NULL,
  `venue`         VARCHAR(255)    NULL DEFAULT NULL,
  `created_at`    TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_matches_team_1_foreign` (`team_1`),
  KEY `team_matches_team_2_foreign` (`team_2`),
  KEY `team_matches_status_index` (`status`),
  KEY `team_matches_scheduled_at_index` (`scheduled_at`),
  CONSTRAINT `team_matches_team_1_foreign` FOREIGN KEY (`team_1`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_matches_team_2_foreign` FOREIGN KEY (`team_2`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 000024: match clock ────────────────────────────────────────────────────
-- "Duplicate column name" here just means this part is already applied.
ALTER TABLE `team_matches`
  ADD COLUMN `timer_started_at`       DATETIME     NULL DEFAULT NULL AFTER `team_2_score`,
  ADD COLUMN `timer_elapsed_seconds`  INT UNSIGNED NOT NULL DEFAULT 0 AFTER `timer_started_at`,
  ADD COLUMN `period`                 VARCHAR(30)  NULL DEFAULT NULL AFTER `timer_elapsed_seconds`;


-- ── 000025: squads, half length, raid clock, court swap ────────────────────
-- These are the columns whose absence makes the clocks show 00:00 and the
-- raid counter stick at 0.
ALTER TABLE `team_matches`
  ADD COLUMN `players_left_1`         TINYINT UNSIGNED  NOT NULL DEFAULT 7    AFTER `team_1_score`,
  ADD COLUMN `players_left_2`         TINYINT UNSIGNED  NOT NULL DEFAULT 7    AFTER `team_2_score`,
  ADD COLUMN `timer_duration_seconds` INT UNSIGNED      NOT NULL DEFAULT 1200 AFTER `timer_elapsed_seconds`,
  ADD COLUMN `raid_team`              TINYINT UNSIGNED  NULL DEFAULT NULL     AFTER `period`,
  ADD COLUMN `raid_started_at`        DATETIME          NULL DEFAULT NULL     AFTER `raid_team`,
  ADD COLUMN `raid_duration_seconds`  SMALLINT UNSIGNED NOT NULL DEFAULT 30   AFTER `raid_started_at`,
  ADD COLUMN `courts_swapped`         TINYINT(1)        NOT NULL DEFAULT 0    AFTER `raid_duration_seconds`;


-- ── 000026: match_events (the match feed and the undo stack) ───────────────
CREATE TABLE IF NOT EXISTS `match_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_match_id` BIGINT UNSIGNED NOT NULL,
  `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `type`          VARCHAR(30)     NOT NULL,
  `team`          TINYINT UNSIGNED NULL DEFAULT NULL,
  `points`        INT             NOT NULL DEFAULT 0,
  `description`   VARCHAR(255)    NULL DEFAULT NULL,
  `snapshot`      JSON            NOT NULL,
  `created_at`    DATETIME        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_events_team_match_id_id_index` (`team_match_id`, `id`),
  KEY `match_events_user_id_foreign` (`user_id`),
  CONSTRAINT `match_events_team_match_id_foreign` FOREIGN KEY (`team_match_id`) REFERENCES `team_matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Record these as applied, so a future `migrate` does not repeat them ────
--
-- The NOT IN guard is what makes this safe to run twice. INSERT IGNORE would
-- not help: Laravel's `migrations` table has no unique index on `migration`,
-- so a second run would simply add duplicate rows.
--
-- The inner `(SELECT ... FROM migrations) AS ...` wrappers are required because
-- MySQL will not let you read the table you are inserting into directly.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT
  m.name,
  (SELECT COALESCE(MAX(b.batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS b)
FROM (
  SELECT '2024_01_01_000022_create_teams_table'                     AS name
  UNION ALL SELECT '2024_01_01_000023_create_team_matches_table'
  UNION ALL SELECT '2024_01_01_000024_add_timer_to_team_matches'
  UNION ALL SELECT '2024_01_01_000025_add_scoreboard_state_to_team_matches'
  UNION ALL SELECT '2024_01_01_000026_create_match_events_table'
) AS m
WHERE m.name NOT IN (SELECT e.migration FROM (SELECT migration FROM `migrations`) AS e);


-- ── Sanity check: every column the live screens read ───────────────────────
-- Expect 12 rows. Anything missing means an ALTER above did not apply.
SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'team_matches'
  AND COLUMN_NAME IN (
    'players_left_1','players_left_2','timer_started_at','timer_elapsed_seconds',
    'timer_duration_seconds','period','raid_team','raid_started_at',
    'raid_duration_seconds','courts_swapped','team_1_score','team_2_score'
  )
ORDER BY COLUMN_NAME;
