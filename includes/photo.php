<?php
function save_photo(array $f, string $sub): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return [null, null]; }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 8 * 1024 * 1024) { return [null, 'err_photo']; }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!$info || !isset($types[$info[2]]) || !function_exists($types[$info[2]])) { return [null, 'err_photo']; }
    $make = $types[$info[2]];
    $img = @$make($f['tmp_name']);
    if (!$img) { return [null, 'err_photo']; }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($f['tmp_name']);
        $o = $ex['Orientation'] ?? 1;
        if ($o === 3) { $img = imagerotate($img, 180, 0); }
        elseif ($o === 6) { $img = imagerotate($img, -90, 0); }
        elseif ($o === 8) { $img = imagerotate($img, 90, 0); }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $max = 900;
    if ($w > $max || $h > $max) {
        $s = $max / max($w, $h);
        $n = imagecreatetruecolor((int)round($w * $s), (int)round($h * $s));
        imagecopyresampled($n, $img, 0, 0, 0, 0, imagesx($n), imagesy($n), $w, $h);
        $img = $n;
    }
    $dir = __DIR__ . '/../uploads/' . $sub;
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    $name = bin2hex(random_bytes(12)) . '.jpg';
    if (!imagejpeg($img, $dir . '/' . $name, 82)) { return [null, 'err_photo']; }
    return [$name, null];
}