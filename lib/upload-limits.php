<?php

/**
 * @return int
 */
function parseMaxUploadSizeFromIni() {
  static $max_size = -1;

  if ($max_size < 0) {
    // Start with post_max_size.
    $post_max_size = parseIniSize(ini_get('post_max_size'));
    if ($post_max_size > 0) {
      $max_size = $post_max_size;
    }

    // If upload_max_size is less, then reduce. Except if upload_max_size is
    // zero, which indicates no limit.
    $upload_max = parseIniSize(ini_get('upload_max_filesize'));
    if ($upload_max > 0 && $upload_max < $max_size) {
      $max_size = $upload_max;
    }
  }
  return $max_size;
}

/**
 * @param string $size
 * @return int
 */
function parseIniSize($size) {
  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
  if ($unit) {
    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  }
  else {
    return round($size);
  }
}


// may later on be modified by asset specific overrides
global $maxFileUploadSize;
$maxFileUploadSize = parseMaxUploadSizeFromIni();

const UPLOAD_LIMITS = [
	ASSETTYPE_MOD => [
		'allowedTypes'    => ['png', 'jpg', 'gif'],
		'attachmentCount' => 12,
		'individualSize'  => 2 * MB,
	],
	ASSETTYPE_RELEASE => [
		'allowedTypes'    => ['dll', 'zip', 'cs'],
		'attachmentCount' => 1,
		'individualSize'  => 40 * MB,
	],
];

