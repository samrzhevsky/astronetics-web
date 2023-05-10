<?php

error_reporting(E_ALL);
header('Content-Type: image/png');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/utils.class.php';

$config = require __DIR__ . '/config.php';
$db = new Medoo\Medoo($config['db']);

if (
    !isset($_GET['id']) ||
    empty($_GET['id']) ||
    !($user = $db->get('users', ['id[Int]', 'firstname', 'lastname', 'midname', 'cert_id', 'cert_date', 'cert_saved[Bool]'], ['cert_id' => $_GET['id']])) ||
    is_null($user['firstname']) ||
    is_null($user['lastname'])
) {
    exit;
}

if ($user['cert_saved']) {// если сертификат уже нарисован, то выдаём готовый
    readfile(__DIR__ . '/certs/' . $user['cert_id'] . '.png');
} else {
    $image = imagecreatefrompng(__DIR__ . '/assets/cert_template.png');
    $font_color = imagecolorallocate($image, 0, 0, 0);
    $font = __DIR__ . '/assets/Garet-Book.ttf';

    // Вывод ФИО
    $fullName = ucfirst($user['lastname']) . ' ' . ucfirst($user['firstname']);
    if (!is_null($user['midname'])) {
        $fullName .= ' ' . ucfirst($user['midname']);
    }

    $font_size = 60;
    $text_size = imagettfbbox($font_size, 0, $font, $fullName);
    $text_x = floor((imagesx($image) - $text_size[2] - 10) / 2);
    $text_y = floor((imagesy($image) - $text_size[3] + 60) / 2);
    Utils::addTextToImage($image, $fullName, $font, $font_size, $text_x, $text_y);


    // Вывод суммы баллов
    $score = $db->sum('tests', 'result', ['user_id' => $user['id'], 'completed' => 1]);
    $result = $score . ' ' . Utils::declension($score, 'балл', 'балла', 'баллов');
    $font_size = 24;
    $text_x = 1050;
    $text_y = 907;
    Utils::addTextToImage($image, $result, $font, $font_size, $text_x, $text_y);


    // Вывод даты
    $date = date('d.m.Y', strtotime($user['cert_date']));
    $font_size = 20;
    $text_x = 520;
    $text_y = 1150;
    Utils::addTextToImage($image, $date, $font, $font_size, $text_x, $text_y);


    // Вывод уникального кода
    $font_size = 20;
    $text_x = 1235;
    $text_y = 1150;
    Utils::addTextToImage($image, $user['cert_id'], $font, $font_size, $text_x, $text_y);

    if (!is_dir(__DIR__ . '/certs')) {
        mkdir(__DIR__ . '/certs');
    }

    imagepng($image);
    imagepng($image, __DIR__ . '/certs/' . $user['cert_id'] . '.png');
    imagedestroy($image);

    $db->update('user', ['cert_saved' => 1], ['id' => $user['id']]);
}
