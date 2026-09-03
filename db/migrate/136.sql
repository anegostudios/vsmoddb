USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='notifications' AND INDEX_NAME='userid_read_created') ) THEN

  CREATE INDEX userid_read_created ON notifications(userId, `read`, created);
  CREATE INDEX kind_recordid ON notifications(kind, recordId);
  DROP INDEX userid ON notifications;

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;


