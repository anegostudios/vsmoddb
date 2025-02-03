CREATE TABLE IF NOT EXISTS moddb.commentwebhook (
    userid INT NULL,
    linkurl VARCHAR(512) NOT NULL,
    username VARCHAR(128) NOT NULL,
    isComment BOOL NOT NULL)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS moddb.followwebhook (
   id INT NOT NULL AUTO_INCREMENT,
   data VARCHAR(2048) NOT NULL,
    PRIMARY KEY (id) )
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS moddb.followwebhookuser (
   followwebhookid INT NULL,
   userid INT NULL)
ENGINE = InnoDB;

alter table moddb.user
    add fwhFails int default 0 not null;

alter table moddb.user
    add cwhFails int default 0 not null;
