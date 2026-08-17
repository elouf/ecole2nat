<?php

namespace Ecole2Nat\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class ScheduleDurationCalculator
{
    public static function endTime(?string $startTime, int $durationMinutes, ?\DateTimeZone $timezone = null): ?string
    {
        if ($startTime === null || $startTime === '' || $durationMinutes <= 0) {
            return null;
        }
        $timezone ??= wp_timezone();
        $start = \DateTimeImmutable::createFromFormat('!H:i:s', $startTime, $timezone);
        return $start ? $start->modify('+' . $durationMinutes . ' minutes')->format('H:i:s') : null;
    }

    public static function minutes(?string $startTime, ?string $endTime, ?\DateTimeZone $timezone = null): ?int
    {
        if ($startTime === null || $endTime === null || $startTime === '' || $endTime === '') {
            return null;
        }

        $timezone ??= wp_timezone();
        $start = \DateTimeImmutable::createFromFormat('!H:i:s', $startTime, $timezone);
        $end = \DateTimeImmutable::createFromFormat('!H:i:s', $endTime, $timezone);
        if (!$start || !$end) {
            return null;
        }
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        $minutes = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
        return $minutes > 0 ? $minutes : null;
    }
}
