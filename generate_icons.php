<?php
// Genereer 192x192 icoon
graphic_icon(192, 70, 85, 'assets/img/icon-192.png');
// Genereer 512x512 icoon
graphic_icon(512, 220, 235, 'assets/img/icon-512.png');

define('ICON_BG_R', 26);
define('ICON_BG_G', 35);
define('ICON_BG_B', 50);
define('ICON_FG_R', 255);
define('ICON_FG_G', 255);
define('ICON_FG_B', 255);

function graphic_icon(int $size, int $textX, int $textY, string $outputPath): void {
    $img = imagecreate($size, $size);
    $bg  = imagecolorallocate($img, ICON_BG_R, ICON_BG_G, ICON_BG_B);
    $fg  = imagecolorallocate($img, ICON_FG_R, ICON_FG_G, ICON_FG_B);
    imagefill($img, 0, 0, $bg);
    imagestring($img, 5, $textX, $textY, 'AT', $fg);
    imagepng($img, __DIR__ . '/' . $outputPath);
    imagedestroy($img);
}

echo "Iconen aangemaakt!\n";
