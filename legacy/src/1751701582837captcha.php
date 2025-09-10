<?php
// captcha.php — 3D псевдо-капча с эффектами
session_start();

header('Content-type: image/png');

$width = 180;
$height = 60;
$image = imagecreatetruecolor($width, $height);

$bg_color = imagecolorallocate($image, 245, 245, 245);
imagefill($image, 0, 0, $bg_color);

$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
$captcha_text = '';
for ($i = 0; $i < 5; $i++) {
    $captcha_text .= $chars[random_int(0, strlen($chars) - 1)];
}
$_SESSION['captcha'] = $captcha_text;

$font = __DIR__ . '/fonts/Roboto-Regular.ttf'; // замените на путь к своему TTF-файлу
if (!file_exists($font)) {
    $font = __DIR__ . '/arial.ttf'; // fallback
}

for ($i = 0; $i < 5; $i++) {
    $angle = random_int(-35, 35);
    $size = random_int(20, 28);
    $x = 20 + $i * 30;
    $y = random_int(35, 50);

    // Псевдо-объём: тень
    $shadow_color = imagecolorallocate($image, 100, 100, 100);
    imagettftext($image, $size, $angle, $x + 2, $y + 2, $shadow_color, $font, $captcha_text[$i]);

    // Основной цвет
    $color = imagecolorallocate($image, random_int(0, 120), random_int(0, 120), random_int(0, 120));
    imagettftext($image, $size, $angle, $x, $y, $color, $font, $captcha_text[$i]);
}

// Фоновая сетка
for ($i = 0; $i < 10; $i++) {
    $grid_color = imagecolorallocatealpha($image, 180, 180, 180, 100);
    imageline($image, random_int(0, $width), 0, random_int(0, $width), $height, $grid_color);
    imageline($image, 0, random_int(0, $height), $width, random_int(0, $height), $grid_color);
}

// Пузырьки
for ($i = 0; $i < 30; $i++) {
    $bubble_color = imagecolorallocatealpha($image, 150, 150, 255, 100);
    imagefilledellipse(
        $image,
        random_int(0, $width),
        random_int(0, $height),
        random_int(5, 15),
        random_int(5, 15),
        $bubble_color
    );
}

imagepng($image);
imagedestroy($image);
