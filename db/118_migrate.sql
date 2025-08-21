USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__moderation()
BEGIN

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='notifications' AND COLUMN_NAME='kind' and DATA_TYPE='enum') ) THEN
	ALTER TABLE `notifications` ADD COLUMN `kind_` TINYINT NULL AFTER `kind`;
	UPDATE `notifications`
		SET kind_ = CASE kind
			WHEN 'newcomment'           THEN 1
			WHEN 'mentioncomment'       THEN 2
			WHEN 'newrelease'           THEN 3
			WHEN 'teaminvite'           THEN 4
			WHEN 'modownershiptransfer' THEN 5
			WHEN 'modlocked'            THEN 6
			WHEN 'modunlockrequest'     THEN 7
			WHEN 'modunlocked'          THEN 8
		END;

	ALTER TABLE `notifications` DROP COLUMN `kind`;
	ALTER TABLE `notifications` CHANGE COLUMN `kind_` `kind` TINYINT NOT NULL;
END IF;


END $$

CALL upgrade_database__moderation() $$


