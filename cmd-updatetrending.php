<?php
chdir(dirname(__FILE__));

$config = array();
$config["basepath"] = getcwd() . '/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.at";
$_SERVER["REQUEST_URI"] = "";
define("DEBUG", 1);
include("lib/config.php");
include("lib/core.php");

$ok = $con->execute('
	UPDATE `mod` m
	LEFT JOIN (
		SELECT c.assetid, COUNT(c.commentid) AS comments
		FROM `comment` c
		WHERE c.created > DATE_SUB(NOW(), INTERVAL 72 HOUR)
		GROUP BY c.assetid
	) c1 ON c1.assetid = m.assetid
	LEFT JOIN (
		SELECT r.modid, COUNT(d.lastDownload) as downloads
		FROM FileDownloadTracking d
		JOIN `file` f ON f.fileid = d.fileId
		join `release` r on r.assetid = f.assetid
		WHERE d.lastDownload > DATE_SUB(NOW(), INTERVAL 72 HOUR)
		GROUP BY r.modid
	) f1 ON f1.modid = m.modid
	SET m.trendingpoints = IFNULL(f1.downloads, 0) + 5 * IFNULL(c1.comments, 0)
');

if(!$ok) http_response_code(HTTP_INTERNAL_ERROR);
