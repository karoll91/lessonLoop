<?php

/*
 * Builds every app icon and logo from the source images in
 * mobile/assets/icon (1024x1024 PNG):
 *   icon.png             square, opaque (iOS)
 *   icon_rounded.png     rounded corners, transparent outside (logos, favicons)
 *   icon_foreground.png  Android adaptive icon foreground (inside the safe zone)
 *   icon_background.png  Android adaptive icon background
 *
 * Run after changing the logo:  php deploy/make-icons.php
 * Needs PHP with the gd extension (Open Server Panel's PHP has it).
 */

$root = dirname(__DIR__);
$src = "$root/mobile/assets/icon";

function load(string $path): GdImage
{
    $image = imagecreatefrompng($path) ?: throw new RuntimeException("Cannot read $path");
    imagealphablending($image, false);
    imagesavealpha($image, true);

    return $image;
}

function resized(GdImage $source, int $size, bool $alpha = true): GdImage
{
    $target = imagecreatetruecolor($size, $size);
    if ($alpha) {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
    } else {
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagealphablending($target, true);
    }
    imagecopyresampled($target, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));

    return $target;
}

function save(GdImage $image, string $path, bool $alpha = true): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    imagesavealpha($image, $alpha);
    imagepng($image, $path, 9) ?: throw new RuntimeException("Cannot write $path");
    echo '  ', str_replace($GLOBALS['root'].'/', '', $path), ' ', imagesx($image), "px\n";
}

function png(GdImage $source, int $size, bool $alpha = true): string
{
    ob_start();
    $image = resized($source, $size, $alpha);
    imagesavealpha($image, $alpha);
    imagepng($image, null, 9);

    return (string) ob_get_clean();
}

/** A Windows .ico whose entries are PNG images (supported since Vista). */
function ico(GdImage $source, array $sizes, string $path): void
{
    $images = array_map(fn (int $size): string => png($source, $size), $sizes);
    $header = pack('vvv', 0, 1, count($sizes));
    $offset = 6 + 16 * count($sizes);
    $entries = '';
    foreach ($sizes as $i => $size) {
        $dimension = $size >= 256 ? 0 : $size;
        $entries .= pack('CCCCvvVV', $dimension, $dimension, 0, 0, 1, 32, strlen($images[$i]), $offset);
        $offset += strlen($images[$i]);
    }
    file_put_contents($path, $header.$entries.implode('', $images));
    echo '  ', str_replace($GLOBALS['root'].'/', '', $path), ' ', implode('/', $sizes), "px\n";
}

$square = load("$src/icon.png");
$rounded = load("$src/icon_rounded.png");
$foreground = load("$src/icon_foreground.png");
$background = load("$src/icon_background.png");

// Adaptive icon as one picture: background with the foreground on top.
$layered = resized($background, 1024);
imagealphablending($layered, true);
imagecopy($layered, $foreground, 0, 0, 0, 0, 1024, 1024);

echo "Mobile (in-app logo, Android, iOS, Windows, web):\n";
save(resized($rounded, 512), "$root/mobile/assets/images/logo.png");

$densities = ['mdpi' => 1, 'hdpi' => 1.5, 'xhdpi' => 2, 'xxhdpi' => 3, 'xxxhdpi' => 4];
$res = "$root/mobile/android/app/src/main/res";
foreach ($densities as $density => $scale) {
    save(resized($rounded, (int) round(48 * $scale)), "$res/mipmap-$density/ic_launcher.png");
    save(resized($foreground, (int) round(108 * $scale)), "$res/mipmap-$density/ic_launcher_foreground.png");
    save(resized($background, (int) round(108 * $scale)), "$res/mipmap-$density/ic_launcher_background.png");
}
if (! is_dir("$res/mipmap-anydpi-v26")) {
    mkdir("$res/mipmap-anydpi-v26", 0777, true);
}
file_put_contents("$res/mipmap-anydpi-v26/ic_launcher.xml", <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<!-- Android 8+ adaptive launcher icon (deploy/make-icons.php). -->
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@mipmap/ic_launcher_background" />
    <foreground android:drawable="@mipmap/ic_launcher_foreground" />
</adaptive-icon>

XML);
echo "  mobile/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml\n";

// iOS: every size listed in the asset catalog, without transparency.
$appIcon = "$root/mobile/ios/Runner/Assets.xcassets/AppIcon.appiconset";
foreach (json_decode(file_get_contents("$appIcon/Contents.json"), true)['images'] as $entry) {
    if (! isset($entry['filename'])) {
        continue;
    }
    $size = (int) round((float) explode('x', $entry['size'])[0] * (int) $entry['scale']);
    save(resized($square, $size, false), "$appIcon/{$entry['filename']}", false);
}

ico($rounded, [16, 24, 32, 48, 64, 256], "$root/mobile/windows/runner/resources/app_icon.ico");

save(resized($rounded, 32), "$root/mobile/web/favicon.png");
save(resized($rounded, 192), "$root/mobile/web/icons/Icon-192.png");
save(resized($rounded, 512), "$root/mobile/web/icons/Icon-512.png");
save(resized($layered, 192), "$root/mobile/web/icons/Icon-maskable-192.png");
save(resized($layered, 512), "$root/mobile/web/icons/Icon-maskable-512.png");

echo "Admin panel and board:\n";
foreach (['admin', 'board'] as $app) {
    save(resized($rounded, 64), "$root/$app/public/favicon.png");
    save(resized($rounded, 256), "$root/$app/public/logo.png");
}
