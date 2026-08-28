<?php
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// docs/coin/* 收款码截图统一右下角半透明水印（避开扫码区域）

declare(strict_types=1);

$dir = __DIR__ . '/../docs/coin';
$files = glob($dir . '/*.jpg');
if (!$files) {
    fwrite(STDERR, "no files\n");
    exit(1);
}
foreach ($files as $f) {
    $img = new Imagick($f);
    $w = $img->getImageWidth();
    $draw = new ImagickDraw();
    $draw->setFillColor(new ImagickPixel('rgba(0,0,0,0.38)'));
    $draw->setFontSize(max(28, (int) round($w * 0.045)));
    $draw->setGravity(Imagick::GRAVITY_SOUTHEAST);
    $img->annotateImage($draw, 0, 28, 0, 'erik.xyz');
    $img->setImageFormat('jpeg');
    $img->setImageCompressionQuality(88);
    $img->writeImage($f);
    $img->destroy();
    echo "watermarked: {$f}\n";
}
