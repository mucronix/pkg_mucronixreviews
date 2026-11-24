-- The tables hold the reviews of the site. Whether they go is decided by script.php and the option "Keep the reviews".
-- The templates of the emails go with the extension, the copies of them in "Mail Templates" too (since 0.6.0).
DELETE FROM `#__mail_templates` WHERE `extension` = 'com_mucronixreviews';
