<?php

class Utils
{
    public static function isValidJSON(string $str): bool
    {
        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function declension(int $number, string $declension1, string $declension3, string $declension5): string {
        $result = '';
        $count = $number % 100;

        if ($count >= 5 && $count <= 20) {
            $result = $declension5;
        } else {
            $count = $count % 10;
            if ($count == 1) {
                $result = $declension1;
            } else if ($count >= 2 && $count <= 4) {
                $result = $declension3;
            } else {
                $result = $declension5;
            }
        }

        return $result;
    }

    public static function addTextToImage(GdImage $image, string $text, string $font, int $font_size, int $text_x, int $text_y): array|false {
        $font_color = imagecolorallocate($image, 0, 0, 0);
        return imagefttext($image, $font_size, 0, $text_x, $text_y, $font_color, $font, $text);
    }
}