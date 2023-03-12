<?php
$dir = __DIR__ . "/pictures";
$favicon_path = __DIR__ . "/favicon.ico";
$favicon_url = "https://raw.githubusercontent.com/iamstrawberry/willi-api/main/favicon.ico";
$last_update_file = __DIR__ . "/data.meow";
$repo_api_url = "https://api.github.com/repos/iamstrawberry/willi-api/contents/pictures";
$repo_path = "https://raw.githubusercontent.com/iamstrawberry/willi-api/main/pictures";

// Favicon check
if (!file_exists($favicon_path)) {
  proc_log("Getting favicon.");
  $favicon_data = file_get_contents($favicon_url);
  file_put_contents($favicon_path, $favicon_data);
}

// Check if the image directory exists
if (!file_exists($dir) || !is_dir($dir)) {
  // Create the directory
  proc_log("Setting image directory.");
  if (!mkdir($dir, 0777, true)) {
    die("Error: Failed to create image directory");
  }
}

// Check if it's been less than 30 minutes since the last update
$last_update_time = file_exists($last_update_file)
  ? file_get_contents($last_update_file)
  : 0;
if (time() - $last_update_time >= 1800) {
  proc_log("Fetching images from repository.");
  // Fetch the image list from the repository API
  $context = stream_context_create([
    "http" => ["header" => "User-Agent: PHP"],
  ]);
  $json = file_get_contents($repo_api_url, false, $context);
  $files = [];
  if ($json !== false) {
    $data = json_decode($json, true);
    foreach ($data as $item) {
      if (
        isset($item["type"]) &&
        $item["type"] === "file" &&
        isset($item["download_url"])
      ) {
        $url = $item["download_url"];
        $ext = strtolower(
          pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)
        );
        if (in_array($ext, ["jpg", "jpeg", "png"])) {
          $filename = $dir . "/" . basename($url);
          if (
            !file_exists($filename) ||
            time() - filemtime($filename) >= 1800
          ) {
            // Download the image if it doesn't exist or it's been more than 30 minutes since the last download
            $image_data = file_get_contents($url, false, $context);
            if ($image_data !== false) {
              file_put_contents($filename, $image_data);
            }
          }
          $files[] = $filename;
        }
      }
    }
  }
  // Update the last update time
  file_put_contents($last_update_file, time());
} else {
  // Get the list of image files from the directory
  $files = glob($dir . "/*.{jpg,jpeg,png}", GLOB_BRACE);
}

if (count($files) > 0) {
  // Select a random image from the array
  $img = $files[array_rand($files)];

  // Check if scale and/or style arguments are provided in the URL
  if (isset($_GET["scale"]) || isset($_GET["style"])) {
    // Get the original image size
    $info = getimagesize($img);

    // Set the scale variable based on the scale parameter if it's provided
    if (isset($_GET["scale"])) {
      $scale = $_GET["scale"];
      if (preg_match('/^(\d+)%$/', $scale, $matches)) {
        $scale_value = intval($matches[1]);
        if ($scale_value < 5 || $scale_value > 500) {
          die(
            "wQutils@8.4.1<br>Error: Scale parameter must be between 5% and 500%"
          );
        }
        $scale = $scale_value / 100;
        $height = round($info[1] * $scale);
        $width = round($info[0] * $scale);
      } else {
        die("wQutils@8.4.1<br>Error: Invalid scale parameter");
      }
    } else {
      $height = $info[1];
      $width = $info[0];
    }

    // Check if style argument is provided in the URL
    $style = isset($_GET["style"]) ? $_GET["style"] : "";

    // Calculate the aspect ratio of the original image and the resized image
    $aspect_ratio = $info[0] / $info[1];
    $new_aspect_ratio = $width / $height;

    // Calculate the actual height and width to use for resizing while maintaining aspect ratio
    if ($new_aspect_ratio > $aspect_ratio) {
      $new_width = round($height * $aspect_ratio);
      $new_height = $height;
    } else {
      $new_width = $width;
      $new_height = round($width / $aspect_ratio);
    }

    // Load the image and resize it to the specified dimensions
    $resized_img = imagecreatetruecolor($new_width, $new_height);
    $source_img = imagecreatefromstring(file_get_contents($img));
    imagecopyresampled(
      $resized_img,
      $source_img,
      0,
      0,
      0,
      0,
      $new_width,
      $new_height,
      imagesx($source_img),
      imagesy($source_img)
    );

    // Apply image styles if specified
    if ($style === "monochrome") {
      imagefilter($resized_img, IMG_FILTER_GRAYSCALE);
    } elseif ($style === "invertedcolor") {
      imagefilter($resized_img, IMG_FILTER_NEGATE);
    }

    // Set the Content-Type header to the appropriate image type
    header("Content-Type: image/jpeg");

    // Output the resized image file
    imagejpeg($resized_img);
    imagedestroy($resized_img);
    imagedestroy($source_img);
  } else {
    // Get the image size and type
    $info = getimagesize($img);

    // Set the Content-Type header to the appropriate image type
    header("Content-Type: " . $info["mime"]);
    header("Access-Control-Allow-Origin: *");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    // Output the image file
    readfile($img);
  }
} else {
  die("Error: No image files found");
}
function proc_log($text)
{
  $timestamp = date("Y-m-d H:i:s");
  $log_message = "[{$timestamp}] {$text}" . PHP_EOL;
  file_put_contents(__DIR__ . "/log.meow", $log_message, FILE_APPEND);
}
?>