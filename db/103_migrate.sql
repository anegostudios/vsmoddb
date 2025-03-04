DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_database__moderation $$
CREATE PROCEDURE upgrade_database__moderation()
BEGIN

DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
  ROLLBACK;
  RESIGNAL;
END;


IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND TABLE_NAME='teammembers'
  AND COLUMN_NAME='transferownership') ) THEN
  START TRANSACTION;
    -- these should already exist, but just in case we create invite notifications for non-accepted teammembers before dropping the 'accepted' column
    INSERT INTO moddb.notification (userid, `type`, `recordid`, `created`)
      SELECT tm.userid, 'teaminvite', tm.modid, tm.created
      FROM `teammembers` tm
      LEFT JOIN `notification` n ON n.`read` = 0 AND n.type = 'teaminvite' AND n.userid = tm.userid AND n.recordid = tm.modid
      WHERE tm.accepted = 0 AND n.notificationid IS NULL;

    ALTER TABLE moddb.teammembers DROP COLUMN accepted;

    -- these should already exist, but just in case we create transfer notifications for non-accepted transfers before dropping the 'transferownership' column
    INSERT INTO moddb.notification (userid, `type`, `recordid`, `created`)
      SELECT tm.userid, 'modownershiptransfer', tm.modid, tm.created
      FROM `teammembers` tm
      LEFT JOIN `notification` n ON n.`read` = 0 AND n.type = 'modownershiptransfer' AND n.userid = tm.userid AND n.recordid = tm.modid
      WHERE tm.transferownership = 1 AND n.notificationid IS NULL;

    ALTER TABLE moddb.teammembers DROP COLUMN transferownership;
    
    ALTER TABLE moddb.teammembers RENAME TO moddb.teammember; -- needs to be singular for shortcut functions
  COMMIT;
END IF;

END $$

CALL upgrade_database__moderation() $$

DELIMITER ;
