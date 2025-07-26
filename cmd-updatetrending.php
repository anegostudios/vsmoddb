<?php
chdir(dirname(__FILE__));

$config = array();
$config["basepath"] = getcwd() . '/';
$_SERVER["SERVER_NAME"] = "mods.vintagestory.at";
$_SERVER["REQUEST_URI"] = "";
define("DEBUG", 1);
include("lib/config.php");
include("lib/core.php");

$ok = $con->execute(<<<SQL
	UPDATE Mods m
	LEFT JOIN (
		SELECT c.assetId, COUNT(c.commentId) AS comments
		FROM Comments c
		WHERE c.created > DATE_SUB(NOW(), INTERVAL 72 HOUR)
		GROUP BY c.assetId
	) c1 ON c1.assetId = m.assetId
	LEFT JOIN (
		SELECT r.modId, COUNT(d.lastDownload) as downloads
		FROM FileDownloadTracking d
		JOIN Files f ON f.fileId = d.fileId
		join ModReleases r on r.assetId = f.assetId
		WHERE d.lastDownload > DATE_SUB(NOW(), INTERVAL 72 HOUR)
		GROUP BY r.modId
	) f1 ON f1.modId = m.modId
	SET m.trendingPoints = IFNULL(f1.downloads, 0) + 5 * IFNULL(c1.comments, 0)
SQL);

if(!$ok) http_response_code(HTTP_INTERNAL_ERROR);
