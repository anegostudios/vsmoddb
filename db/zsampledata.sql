-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';


-- -----------------------------------------------------
-- Data for table `moddb`.`tag`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`tag` (assettypeid, tagtypeid, name, text, color, created) VALUES (2, 1, 'v1.17.4', NULL, '#C9C9C9', NULL);

COMMIT;


-- -----------------------------------------------------
-- Data for table `moddb`.`user`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`user` (roleid, uid, name, password, email, actiontoken, sessiontoken, sessiontokenvaliduntil, timezone, created, lastmodified, lastonline) VALUES (3, NULL, 'Example User', NULL, 'example.user@example.com', NULL, NULL, NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`user` (userid, name, created, banneduntil, email) VALUES (2, 'Evil User', NOW(), '9999-12-31', '2+void@localhost');
INSERT INTO `moddb`.`user` (userid, name, created, email) VALUES (3, 'Moderator User', NOW(), '3+void@localhost');

COMMIT;


-- -----------------------------------------------------
-- Data for table `moddb`.`mod`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (1, 'examplemodone', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (2, 'examplemodtwo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (3, 'examplemodthree', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (4, 'examplemodfour', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (5, 'examplemodfive', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (6, 'examplemodsix', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (7, 'examplemodseven', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (8, 'examplemodeight', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (9, 'examplemodnine', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);
INSERT INTO `moddb`.`mod` (assetid, urlalias, logofileid, logofilename, homepageurl, sourcecodeurl, trailervideourl, issuetrackerurl, wikiurl, comments, side, created, lastreleased, supportedversions) VALUES (10, 'examplemodten', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'both', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);

COMMIT;


-- -----------------------------------------------------
-- Data for table `moddb`.`asset`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 1', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 2', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 3', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 4', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 5', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 6', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 7', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 8', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 9', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);
INSERT INTO `moddb`.`asset` (createdbyuserid, editedbyuserid, statusid, assettypeid, code, name, text, tagscached, created, lastmodified, numsaved) VALUES (1,1,2,1,NULL,'Example Mod 10', '<p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque leo sem, ultrices vel enim vel, pretium fringilla nisi. Nunc ac massa hendrerit, semper est sed, blandit eros. Sed at placerat lorem, viverra lacinia nibh. Mauris eu nunc a augue rhoncus pharetra ac eu nulla. Fusce elementum sapien sit amet sapien pellentesque, eget porttitor quam eleifend. Maecenas imperdiet justo dolor, id bibendum purus ornare vel. Morbi commodo porttitor nisi, sed finibus eros blandit eget. Nulla quis rhoncus urna.</p>', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1);

COMMIT;

-- -----------------------------------------------------
-- Data for table `moddb`.`moderationrecord`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;
INSERT INTO  `moddb`.`moderationrecord` (targetuserid, kind, until, moderatorid, reason) VALUES (2, 2, '9999-12-31', 3, '');
INSERT INTO  `moddb`.`moderationrecord` (targetuserid, kind, until, moderatorid, reason) VALUES (2, 1, '9999-12-31', 3, 'Comment: bad comment');

COMMIT;

-- -----------------------------------------------------
-- Data for table `moddb`.`comment`
-- -----------------------------------------------------
START TRANSACTION;
USE `moddb`;

INSERT INTO  `moddb`.`comment` (assetid, userid, text, created) VALUES (1, 2, 'normal comment', NOW());
INSERT INTO  `moddb`.`comment` (assetid, userid, text, created) VALUES (1, 2, 'ok comment', NOW());
INSERT INTO  `moddb`.`comment` (assetid, userid, text, created, lastmodaction, deleted) VALUES (1, 2, 'bad comment', NOW(), 1, 1);

COMMIT;
