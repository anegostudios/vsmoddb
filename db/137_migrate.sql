USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database__fulltext_search()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='mods' AND INDEX_NAME='ft_mods_search') ) THEN

  CREATE FULLTEXT INDEX ft_mods_search ON mods(summary, descriptionSearchable);

END IF;

IF NOT EXISTS( (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='assets' AND INDEX_NAME='ft_assets_name') ) THEN

  CREATE FULLTEXT INDEX ft_assets_name ON assets(name);

END IF;


END $$

CALL upgrade_database__fulltext_search() $$

DELIMITER ;
