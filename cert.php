<?php

error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';
$db = new Medoo\Medoo($config['db']);

if (
    !isset($_GET['id']) ||
    empty($_GET['id']) ||
    !$cert = $db->get('certificates', ['user_id', 'created_at'], ['unique_id' => $_GET['id']]) ||
    !$user = $db->get('users', ['firstname', 'lastname', 'midname'], ['id' => $cert['user_id']]) ||
    is_null($user['firstname']) ||
    is_null($user['lastname'])
) {
    exit;
}

header('Content-Type: image/png');

// Set the text to be displayed
$text = ucfirst($user['lastname']) . ' ' . ucfirst($user['firstname']);
if (!is_null($user['midname'])) {
    $text .= ' ' . ucfirst($user['midname']);
}

// Load the image
$image = imagecreatefrompng('image.png');

// Set the font size and color for the text
$font_size = 20;
$font_color = imagecolorallocate($image, 255, 255, 255);

// Get the size of the text
$text_size = imagettfbbox($font_size, 0, 'arial.ttf', $text);

// Calculate the position of the text
$text_x = imagesx($image) - $text_size[2] - 10;
$text_y = imagesy($image) - $text_size[3] - 10;

// Add the text to the image
imagettftext($image, $font_size, 0, $text_x, $text_y, $font_color, 'arial.ttf', $text);

imagepng($image);
imagedestroy($image);
