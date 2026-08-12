<?php

namespace Ecole2Nat\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class GroupScheduleParser
{
    /**
     * @return array{weekday:?int,start_time:?string}
     */
    public static function parse(string $label): array
    {
        $normalized = remove_accents(mb_strtolower(trim($label)));

        $weekdays = [
            'lundi' => 1,
            'mardi' => 2,
            'mercredi' => 3,
            'jeudi' => 4,
            'vendredi' => 5,
            'samedi' => 6,
            'dimanche' => 7,
        ];

        $weekday = null;
        foreach ($weekdays as $name => $value) {
            if (preg_match('/(?:^|\s)' . preg_quote($name, '/') . '(?:\s|$)/u', $normalized)) {
                $weekday = $value;
                break;
            }
        }

        $startTime = null;
        if (preg_match('/\b([01]?\d|2[0-3])\s*[h:]\s*([0-5]\d)?\b/u', $normalized, $matches)) {
            $hour = (int) $matches[1];
            $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
            $startTime = sprintf('%02d:%02d:00', $hour, $minute);
        }

        return [
            'weekday' => $weekday,
            'start_time' => $startTime,
        ];
    }
}
