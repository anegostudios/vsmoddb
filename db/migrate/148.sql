USE `moddb`;

DELIMITER $$

CREATE OR REPLACE PROCEDURE upgrade_database()
BEGIN

IF NOT EXISTS( (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='moddb' AND
 TABLE_NAME='modPeekResults' AND COLUMN_NAME='capabilities') ) THEN

  ALTER TABLE modPeekResults ADD COLUMN capabilities INT1 UNSIGNED   NOT NULL AFTER requiredOnClient;
  ALTER TABLE modPeekResults ADD COLUMN rawReflections TEXT CHARACTER SET latin1 NULL after rawDependencies;

  ALTER TABLE modReleases ADD COLUMN `status` INT1 UNSIGNED NOT NULL AFTER `version`;
  ALTER TABLE mods ADD COLUMN `status` INT1 UNSIGNED NOT NULL AFTER `category`;

  UPDATE mods m JOIN assets a ON a.assetId = m.assetId SET m.status = a.statusId;
  
  UPDATE modReleases r
    LEFT JOIN modReleaseRetractions rr ON rr.releaseId = r.releaseId
    SET r.status = IF(rr.reason is null, 3, 4); -- 3 = released, 4 = retracted

  ALTER TABLE assets DROP COLUMN statusId;
  DROP TABLE `status`;

  ---

  CREATE TABLE IF NOT EXISTS `moderationRequestEvents` (
    `eventId`     INT           NOT NULL AUTO_INCREMENT,
    `requestId`   INT           NOT NULL,
    `userId`      INT           NOT NULL,
    `kind`        INT1 UNSIGNED NOT NULL,
    `data`        INT           NOT NULL,
    `created`     DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`eventId`),
    INDEX `query` (`requestId`, `created`),
    CONSTRAINT `FK_mrevents_request` FOREIGN KEY (`requestId`) REFERENCES `moderationRequests`(`requestId`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `FK_mrevents_user` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE RESTRICT
  );

  CREATE TABLE IF NOT EXISTS `moderationRequestMessages` (
    `eventId`        INT      NOT NULL,
    `text`           TEXT     NOT NULL,
    `textSearchable` TEXT     NOT NULL,
    CONSTRAINT `FK_mrmessages_event` FOREIGN KEY (`eventId`) REFERENCES `moderationRequestEvents`(`eventId`) ON UPDATE CASCADE ON DELETE CASCADE
  );

  INSERT INTO moderationRequestEvents (requestId, userId, kind, data, created)
    SELECT requestId, initiatorUserId, 2, 0, created -- kind 2 = message
    FROM moderationRequests
    WHERE request != '';

  INSERT INTO moderationRequestMessages (eventId, `text`, textSearchable)
    SELECT e.eventId, r.request, r.requestSearchable
    FROM moderationRequestEvents e
    JOIN moderationRequests r ON r.requestId = e.requestId AND r.request != ''
    WHERE e.kind = 2;

  CREATE TEMPORARY TABLE __MSG_DEDUP
    SELECT e.eventId
    FROM moderationRequestEvents e
    JOIN moderationRequests r ON r.requestId = e.requestId AND r.request != ''
    WHERE e.kind = 2;

  INSERT INTO moderationRequestEvents (requestId, userId, kind, data, created)
    SELECT requestId, resolverUserId, 1, stateFlags, resolved -- kind 1 = state change
    FROM moderationRequests
    WHERE stateFlags != 0;

  INSERT INTO moderationRequestEvents (requestId, userId, kind, data, created)
    SELECT requestId, resolverUserId, 2, 0, resolved -- kind 2 = message
    FROM moderationRequests
    WHERE resolution IS NOT NULL;

  INSERT INTO moderationRequestMessages (eventId, `text`, textSearchable)
    SELECT e.eventId, r.resolution, r.resolution -- eh, good enough.
    FROM moderationRequestEvents e
    JOIN moderationRequests r ON r.requestId = e.requestId AND r.resolution IS NOT NULL
    LEFT JOIN __MSG_DEDUP d ON d.eventId = e.eventId
    WHERE e.kind = 2 AND d.eventId IS NULL;

  ALTER TABLE moderationRequests ADD  COLUMN requestEvt INT NULL AFTER request;
  ALTER TABLE moderationRequests ADD  COLUMN resolutionEvt INT NULL AFTER resolution;
  

  UPDATE moderationRequests r
    JOIN moderationRequestEvents e ON e.requestId = r.requestId
    JOIN __MSG_DEDUP d ON d.eventId = e.eventId
    SET r.requestEvt = e.eventId;

  UPDATE moderationRequests r
    JOIN moderationRequestEvents e ON e.requestId = r.requestId
    LEFT JOIN __MSG_DEDUP d ON d.eventId = e.eventId
    SET r.resolutionEvt = e.eventId
    WHERE d.eventId IS NULL AND e.kind = 2;

  -- this one is for spam without a resolution message
  UPDATE moderationRequests r
    JOIN moderationRequestEvents e ON e.requestId = r.requestId
    LEFT JOIN __MSG_DEDUP d ON d.eventId = e.eventId
    SET r.resolutionEvt = e.eventId
    WHERE r.stateFlags & (1 << 7) AND r.resolutionEvt = 0;


  ALTER TABLE moderationRequests DROP COLUMN request;
  ALTER TABLE moderationRequests DROP COLUMN resolution;
  ALTER TABLE moderationRequests DROP COLUMN requestSearchable;


  ---

END IF;


END $$

CALL upgrade_database() $$

DELIMITER ;
