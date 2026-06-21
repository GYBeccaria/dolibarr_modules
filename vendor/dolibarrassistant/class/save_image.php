<?php
// convert axios value
$_POST = json_decode(file_get_contents("php://input"), true);

// message
$url = $_POST["url"];
$message = $_POST["message"];
$hash = date("Y-m-d h-m-i");
$hash = md5($hash);

@$rawImage = file_get_contents($url);
if ($rawImage) {
  file_put_contents("images/image.png", $rawImage);
  echo 'Image Saved';
}
?>