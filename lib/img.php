<?php

function copyImageResized($file, $width = 0, $height = 0, $proportional = true, $output = 'file', $ext = '_thumb', $newfile = '', $crop = null) {
	if ($height <= 0 && $width <= 0) {
		return false;
	}
 
	$info = getimagesize($file);
	list($width_old, $height_old) = $info;
	
	$image = '';
	$final_width = 0;
	$final_height = 0;
	
	if ($info[2] != IMAGETYPE_GIF && 
		$info[2] != IMAGETYPE_JPEG && 
		$info[2] != IMAGETYPE_PNG) return false;
	
	$filename = NULL;
	switch (strtolower($output)) {
		case 'browser':
			$mime = image_type_to_mime_type($info[2]);
			header("Content-type: $mime");
			break;
		
		case 'file':
			if (strlen($newfile)) {
				$filename = $newfile;
			} else {
				$filename = preg_replace("/(?U)(.*)(\.\w+)$/","\\1$ext\\2",$file);
			}
			break;
		
		default:
			return false;
			break;
	}
	
	// Don't resize, just copy
	if ($width_old == $width && $height_old == $height && !$crop) {
		copy($file, $filename);
		return true;
	}
	
	if ($proportional) {
	  if ($width == 0) $factor = $height/$height_old;
	  elseif ($height == 0) $factor = $width/$width_old;
	  else $factor = min ( $width / $width_old, $height / $height_old);   
 
	  $final_width = round ($width_old * $factor);
	  $final_height = round ($height_old * $factor);
	  
	} else {
	  $final_width = ( $width <= 0 ) ? $width_old : $width;
	  $final_height = ( $height <= 0 ) ? $height_old : $height;
	}
	
	// Only resize if the picture is actually bigger than the supplied size
	if($width_old < $width && $height_old < $height) {
		$final_width = $width_old;
		$final_height = $height_old;
	}

	switch ($info[2]) {
	  case IMAGETYPE_GIF:
		$image = imagecreatefromgif($file);
	  break;
	  case IMAGETYPE_JPEG:
		$image = imagecreatefromjpeg($file); //TODO(Rennorb) @bug: undefined function. php version? missing lib?
	  break;
	  case IMAGETYPE_PNG:
		$image = imagecreatefrompng($file);
	  break;
	  default:
		return false;
	}

	if ($crop) {
		$image_resized = imagecreatetruecolor( $crop['w'], $crop['h'] );
	} else {
		$image_resized = imagecreatetruecolor( $final_width, $final_height );
	}
 
	if (($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG)) {
		$trnprt_indx = imagecolortransparent($image);

		// If we have a specific transparent color
		if ($trnprt_indx >= 0) {
			// Get the original image's transparent color's RGB values
			$trnprt_color    = imagecolorsforindex($image, $trnprt_indx);
	 
			// Allocate the same color in the new image resource
			$trnprt_indx    = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
	 
			// Completely fill the background of the new image with allocated color.
			imagefill($image_resized, 0, 0, $trnprt_indx);
	 
			// Set the background color for new image to transparent
			imagecolortransparent($image_resized, $trnprt_indx);
	 
		// Always make a transparent background color for PNGs that don't have one allocated already
		} elseif ($info[2] == IMAGETYPE_PNG) {
	   
			// Turn off transparency blending (temporarily)
			imagealphablending($image_resized, false);
	 
			// Create a new transparent color for image
			$color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
	 
			// Completely fill the background of the new image with allocated color.
			imagefill($image_resized, 0, 0, $color);
	 
			// Restore transparency blending
			imagesavealpha($image_resized, true);
		}
	}
 
	if ($crop) {
		$propX = $width_old / $final_width;
		$propY = $height_old / $final_height;
	
		fastimagecopyresampled($image_resized, $image, 0, 0, $crop['x'] * $propX, $crop['y'] * $propY, $crop['w'], $crop['h'], $crop['w'] * $propX, $crop['h'] * $propY);
	} else {
		if ($info[2] == IMAGETYPE_PNG) {
			imagecopyresampled ($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);
		} else {
			fastimagecopyresampled($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);
		}
	}

	switch ($info[2]) {
		case IMAGETYPE_GIF:
			if(!imagegif($image_resized, $filename))
				return false;
			break;
			
		case IMAGETYPE_JPEG:
			if(!imagejpeg($image_resized, $filename, 95))
				return false;
			break;
			
		case IMAGETYPE_PNG:
			if(!imagepng($image_resized, $filename))
				return false;
			break;
			
		default:
			return null;
	}

	if (strtolower($output) == 'file') {
		@chmod($filename, 0664);
		return $filename;
	}
	
	return $filename;
}

function fastimagecopyresampled (&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3) {
  // Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
  // Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
  // Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
  // Author: Tim Eckel - Date: 12/17/04 - Project: FreeRingers.net - Freely distributable.
  //
  // Optional "quality" parameter (defaults is 3).  Fractional values are allowed, for example 1.5.
  // 1 = Up to 600 times faster.  Poor results, just uses imagecopyresized but removes black edges.
  // 2 = Up to 95 times faster.  Images may appear too sharp, some people may prefer it.
  // 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled.
  // 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
  // 5 = No speedup.  Just uses imagecopyresampled, highest quality but no advantage over imagecopyresampled.

  if (empty($src_image) || empty($dst_image)) { return false; }
  if ($quality <= 1) {
    $temp = imagecreatetruecolor ($dst_w + 1, $dst_h + 1);
    imagecopyresized ($temp, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w + 1, $dst_h + 1, $src_w, $src_h);
    imagecopyresized ($dst_image, $temp, 0, 0, 0, 0, $dst_w, $dst_h, $dst_w, $dst_h);
    imagedestroy ($temp);
  } elseif ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
    $tmp_w = $dst_w * $quality;
    $tmp_h = $dst_h * $quality;
    $temp = imagecreatetruecolor ($tmp_w + 1, $tmp_h + 1);
    imagecopyresized ($temp, $src_image, $dst_x * $quality, $dst_y * $quality, $src_x, $src_y, $tmp_w + 1, $tmp_h + 1, $src_w, $src_h);
    imagecopyresampled ($dst_image, $temp, 0, 0, 0, 0, $dst_w, $dst_h, $tmp_w, $tmp_h);
    imagedestroy ($temp);
  } else {
    imagecopyresampled ($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
  }
  return true;
}