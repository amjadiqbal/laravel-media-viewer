<?php

// House rule: no brown, tan or dark/muted orange anywhere in the UI.
it('uses no brown in its stylesheet', function () {
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/media-viewer.css');
    preg_match_all('/#([0-9a-f]{6})\b/i', $css, $matches);

    foreach ($matches[1] as $hex) {
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split($hex, 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $saturation = $max == 0 ? 0 : ($max - $min) / $max;
        $hue = 0;
        if ($max != $min) {
            $hue = match ($max) {
                $r => 60 * fmod(($g - $b) / ($max - $min), 6),
                $g => 60 * (($b - $r) / ($max - $min) + 2),
                default => 60 * (($r - $g) / ($max - $min) + 4),
            };
        }
        $hue = $hue < 0 ? $hue + 360 : $hue;

        expect($hue >= 10 && $hue <= 50 && $saturation > 0.25 && $max < 0.75)->toBeFalse("#{$hex} is brown");
    }
});
