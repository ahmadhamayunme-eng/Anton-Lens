<?php
namespace App\Services;

interface ScreenshotProviderInterface {
    public function capture(string $url, int $width, int $height, string $outputPath): array;
}
