ALTER TABLE `fileDownloadTracking`
  DROP PRIMARY KEY,
  ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
  MODIFY COLUMN `ipAddress` INET6 NOT NULL,
  ADD INDEX `dedup` (`fileId`, `ipAddress`, `lastDownload`);
