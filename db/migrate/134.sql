USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='mods' AND COLUMN_NAME='category' AND COLUMN_DEFAULT='0') ) THEN

  ALTER TABLE mods ALTER COLUMN category DROP DEFAULT;
  UPDATE mods SET category = category + 1; -- preservers highest bit because we only use category 0, 1 and 2 before that(+high bit)

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;


