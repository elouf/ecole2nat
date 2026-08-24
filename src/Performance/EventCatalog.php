<?php

namespace Ecole2Nat\Performance;

if (!defined('ABSPATH')) { exit; }

final class EventCatalog
{
    private const GROUPS = [
        'Papillon' => ['25PAP', '50PAP', '100PAP', '200PAP'],
        'Dos' => ['25DOS', '50DOS', '100DOS', '200DOS'],
        'Brasse' => ['25BRASSE', '50BRASSE', '100BRASSE', '200BRASSE'],
        'Nage libre' => ['25NL', '50NL', '100NL', '200NL', '400NL', '800NL', '1500NL'],
        '4 nages' => ['1004N', '2004N', '4004N'],
    ];

    public static function groups(): array { return self::GROUPS; }
    public static function all(): array { return array_merge(...array_values(self::GROUPS)); }
    public static function contains(string $event): bool { return in_array($event, self::all(), true); }
}
