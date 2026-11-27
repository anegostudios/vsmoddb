USE `moddb`;

CREATE TABLE IF NOT EXISTS `modApiTokens` (
  `tokenId`   INT          NOT NULL AUTO_INCREMENT,
  `modId`     INT          NOT NULL,
  `userId`    INT          NOT NULL, -- creator; the token acts as this user
  `name`      VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL,
  `tokenHash` BINARY(32)   NOT NULL, -- SHA-256 of the full plaintext token
  `created`   DATETIME     NOT NULL DEFAULT NOW(),
  `expires`   DATETIME     NOT NULL,
  `lastUsed`  DATETIME         NULL,
  PRIMARY KEY (`tokenId`),
  UNIQUE INDEX `tokenHash` (`tokenHash`),
  INDEX `modId_userId` (`modId`, `userId`),
  CONSTRAINT `FK_modApiTokens_modId`  FOREIGN KEY (`modId`)  REFERENCES `mods`(`modId`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_modApiTokens_userId` FOREIGN KEY (`userId`) REFERENCES `users`(`userId`) ON UPDATE CASCADE ON DELETE CASCADE
);
