<?php
namespace App\Services;

class PlaceholderScreenshotProvider implements ScreenshotProviderInterface {
    public function capture(string $url, int $width, int $height, string $outputPath): array {
        if (!function_exists('imagecreatetruecolor')) {
            file_put_contents($outputPath, 'Screenshot placeholder for ' . $url);
            return ['mime' => 'text/plain', 'width' => null, 'height' => null];
        }
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 248, 249, 251);
        $text = imagecolorallocate($img, 26, 26, 26);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
        imagestring($img, 5, 20, 20, 'Anton Lens Screenshot Placeholder', $text);
        imagestring($img, 4, 20, 50, substr($url, 0, 120), $text);
        imagepng($img, $outputPath);
        imagedestroy($img);
        return ['mime' => 'image/png', 'width' => $width, 'height' => $height];
    }
}
