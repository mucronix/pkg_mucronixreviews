-- Reviews have no title. Tables that have none already stay as they are.
ALTER TABLE `#__mucronixreviews_reviews` DROP COLUMN `title` /** CAN FAIL **/;
