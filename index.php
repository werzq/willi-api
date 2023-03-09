<?php
$dir = __DIR__ . '/pictures'; // replace with the name of your image directory
$files = glob($dir . '/*.{jpg,jpeg,png}', GLOB_BRACE); // array of all image files in the directory
$img = $files[array_rand($files)]; // select a random image from the array

// Check if scale and/or style arguments are provided in the URL
if(isset($_GET['scale']) || isset($_GET['style'])) {
  // Get the original image size
  $info = getimagesize($img);

  // Set the scale variable based on the scale parameter if it's provided
  if(isset($_GET['scale'])) {
    $scale = $_GET['scale'];
    if(preg_match('/^(\d+)%$/', $scale, $matches)) {
      $scale_value = intval($matches[1]);
      if ($scale_value < 5 || $scale_value > 500) {
        die('wQutils@8.4.1<br>Error: Scale parameter must be between 5% and 500%');
      }
      $scale = $scale_value / 100;
      $height = round($info[1] * $scale);
      $width = round($info[0] * $scale);
    } else {
      die('wQutils@8.4.1<br>Error: Invalid scale parameter');
    }
  } else {
    $height = $info[1];
    $width = $info[0];
  }
  
  // Check if style argument is provided in the URL
  $style = isset($_GET['style']) ? $_GET['style'] : '';

  // Calculate the aspect ratio of the original image and the resized image
  $aspect_ratio = $info[0] / $info[1];
  $new_aspect_ratio = $width / $height;
  
  // Calculate the actual height and width to use for resizing while maintaining aspect ratio
  if($new_aspect_ratio > $aspect_ratio) {
    $new_width = round($height * $aspect_ratio);
    $new_height = $height;
  } else {
    $new_width = $width;
    $new_height = round($width / $aspect_ratio);
  }
  
  // Load the image and resize it to the specified dimensions
  $resized_img = imagecreatetruecolor($new_width, $new_height);
  $source_img = imagecreatefromstring(file_get_contents($img));
  imagecopyresampled($resized_img, $source_img, 0, 0, 0, 0, $new_width, $new_height, imagesx($source_img), imagesy($source_img));
  
  // Apply image styles if specified
  if($style === 'monochrome') {
    imagefilter($resized_img, IMG_FILTER_GRAYSCALE);
  } elseif($style === 'invertedcolor') {
    imagefilter($resized_img, IMG_FILTER_NEGATE);
  }
  
  // Set the Content-Type header to the appropriate image type
  header('Content-Type: image/jpeg');
  
  // Output the resized image file
  imagejpeg($resized_img);
  imagedestroy($resized_img);
  imagedestroy($source_img);
} else {
  // Get the image size and type
  $info = getimagesize($img);
  
  // Set the Content-Type header to the appropriate image type
  header('Content-Type: '.$info['mime']);
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  
  // Output the image file
  readfile($img);
}
?>