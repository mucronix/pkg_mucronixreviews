CREATE TABLE IF NOT EXISTS `#__mucronixreviews_reviews` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `context`       VARCHAR(100)     NOT NULL,
  `item_id`       INT UNSIGNED     NOT NULL,
  `user_id`       INT UNSIGNED     DEFAULT NULL,
  `username`      VARCHAR(150)     NOT NULL DEFAULT '',
  `rating`        TINYINT UNSIGNED NOT NULL,
  `text`          TEXT             NULL,
  `state`         TINYINT          NOT NULL DEFAULT 0,
  `created`       DATETIME         NOT NULL,
  `modified`      DATETIME         DEFAULT NULL,
  `reply`         TEXT             NULL,
  `reply_created` DATETIME         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_item` (`context`, `item_id`, `user_id`),
  KEY `idx_item_state` (`context`, `item_id`, `state`, `created`),
  KEY `idx_moderation` (`state`, `created`),
  KEY `idx_user`       (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mucronixreviews_stats` (
  `context`        VARCHAR(100)  NOT NULL,
  `item_id`        INT UNSIGNED  NOT NULL,
  `rating_count`   INT UNSIGNED  NOT NULL DEFAULT 0,
  `text_count`     INT UNSIGNED  NOT NULL DEFAULT 0,
  `rating_sum`     INT UNSIGNED  NOT NULL DEFAULT 0,
  `rating_average` DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
  `rating_1`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `rating_2`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `rating_3`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `rating_4`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `rating_5`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `modified`       DATETIME      DEFAULT NULL,
  PRIMARY KEY (`context`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The emails to the moderators, see "System - Mail Templates" (since 0.6.0)
INSERT IGNORE INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('com_mucronixreviews.moderation', 'com_mucronixreviews', '', 'COM_MUCRONIXREVIEWS_MAIL_MODERATION_SUBJECT', 'COM_MUCRONIXREVIEWS_MAIL_MODERATION_BODY', '', '', '{"tags":["sitename","reason","object","object_url","author","rating","text","review_url","list_url"]}'),
('com_mucronixreviews.moderation_more', 'com_mucronixreviews', '', 'COM_MUCRONIXREVIEWS_MAIL_MODERATION_MORE_SUBJECT', 'COM_MUCRONIXREVIEWS_MAIL_MODERATION_MORE_BODY', '', '', '{"tags":["sitename","count","list_url"]}');
