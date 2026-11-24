-- The emails to the moderators (since 0.6.0).
INSERT IGNORE INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('com_mucronixreviews.moderation', 'com_mucronixreviews', '', 'COM_MUCRONIXREVIEWS_MAIL_MODERATION_SUBJECT', 'COM_MUCRONIXREVIEWS_MAIL_MODERATION_BODY', '', '', '{"tags":["sitename","reason","object","object_url","author","rating","text","review_url","list_url"]}'),
('com_mucronixreviews.moderation_more', 'com_mucronixreviews', '', 'COM_MUCRONIXREVIEWS_MAIL_MODERATION_MORE_SUBJECT', 'COM_MUCRONIXREVIEWS_MAIL_MODERATION_MORE_BODY', '', '', '{"tags":["sitename","count","list_url"]}');
