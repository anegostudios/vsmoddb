<?php

require(__DIR__.'/lib/config.php');
require(SRC_ROOT.'/lib/core.php');
if(DB_READONLY) {
	http_response_code(HTTP_SERVICE_UNAVAILABLE);
	exit();
}

$smoothingBias = 5;
$intervalHours = 24;

$ok = $con->execute(<<<SQL
	UPDATE mods m
	LEFT JOIN (
		SELECT c.assetId, COUNT(*) AS comments
		FROM comments c
		WHERE c.created > DATE_SUB(NOW(), INTERVAL $intervalHours HOUR) AND !c.deleted
		GROUP BY c.assetId
	) c1 ON c1.assetId = m.assetId
	LEFT JOIN (
		SELECT c.assetId, COUNT(*) AS comments
		FROM comments c
		WHERE c.created BETWEEN DATE_SUB(NOW(), INTERVAL 2 * $intervalHours HOUR) AND DATE_SUB(NOW(), INTERVAL $intervalHours HOUR) AND !c.deleted
		GROUP BY c.assetId
	) c2 ON c2.assetId = m.assetId
	LEFT JOIN (
		SELECT r.modId, COUNT(*) as downloads
		FROM fileDownloadTracking d
		JOIN files f ON f.fileId = d.fileId
		JOIN modReleases r ON r.assetId = f.assetId
		WHERE d.lastDownload > DATE_SUB(NOW(), INTERVAL $intervalHours HOUR)
		GROUP BY r.modId
	) f1 ON f1.modId = m.modId
	LEFT JOIN (
		SELECT r.modId, COUNT(*) as downloads
		FROM fileDownloadTracking d
		JOIN files f ON f.fileId = d.fileId
		JOIN modReleases r ON r.assetId = f.assetId
		WHERE d.lastDownload BETWEEN DATE_SUB(NOW(), INTERVAL 2 * $intervalHours HOUR) AND DATE_SUB(NOW(), INTERVAL $intervalHours HOUR)
		GROUP BY r.modId
	) f2 ON f2.modId = m.modId
	SET m.trendingPoints = ROUND
  (
    (
        (COALESCE(f1.downloads, 0) + $smoothingBias)
      / (COALESCE(f2.downloads, 0) + $smoothingBias)
    ) + 
    (
        (COALESCE(c1.comments,  0) + $smoothingBias)
      / (COALESCE(c2.comments, 0)  + $smoothingBias)
    ) * 5 - 6
  )
SQL);

if(!$ok) http_response_code(HTTP_INTERNAL_ERROR);
