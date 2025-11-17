USE `moddb`;

CREATE TABLE IF NOT EXISTS `modReleaseFileDependencies` (
  `fileId`     INT             NOT NULL,
  `identifier` VARCHAR(255)        NULL,
  `minVersion` BIGINT UNSIGNED NOT NULL,
	UNIQUE INDEX (`fileId`, `identifier`),
  CONSTRAINT `FK_modPeekResults_fileId` FOREIGN KEY (`fileId`) REFERENCES `files`(`fileId`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE = InnoDB;
