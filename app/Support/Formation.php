<?php

namespace App\Support;

/**
 * A formation is the outfield lines from defence to attack, e.g. "3-2-1" (the GK is implied).
 * layout() turns it into position codes + x/y percentages on a vertical pitch (GK at the bottom).
 */
class Formation
{
    /** Suggested formations keyed by number of outfield players. First one is the default. */
    public const PRESETS = [
        2 => ['1-1', '2'],
        3 => ['2-1', '1-2', '1-1-1'],
        4 => ['1-2-1', '2-2', '3-1', '2-1-1'],
        5 => ['2-2-1', '3-1-1', '2-1-2', '1-3-1'],
        6 => ['3-2-1', '2-3-1', '3-1-2', '2-2-2'],
        7 => ['3-3-1', '3-2-2', '2-3-2', '3-1-3'],
        8 => ['3-3-2', '4-3-1', '3-4-1', '3-2-3'],
        9 => ['4-3-2', '3-4-2', '4-4-1', '3-3-3'],
        10 => ['4-4-2', '4-3-3', '3-5-2', '4-2-3-1'],
    ];

    public const MIN_PLAYERS = 3;

    public const MAX_PLAYERS = 11;

    public static function presets(int $players): array
    {
        return self::PRESETS[$players - 1] ?? [];
    }

    public static function default(int $players): string
    {
        return self::presets($players)[0] ?? (string) ($players - 1);
    }

    /** True when $formation is "n-n-..." and its lines add up to the outfield count. */
    public static function valid(string $formation, int $players): bool
    {
        return (bool) preg_match('/^[1-9](-[1-9]){0,4}$/', $formation)
            && array_sum(explode('-', $formation)) === $players - 1;
    }

    /** @return list<array{0: string, 1: int, 2: int}> [code, x%, y%], GK first */
    public static function layout(string $formation): array
    {
        $lines = array_map('intval', explode('-', $formation));
        $count = count($lines);
        $positions = [['GK', 50, 88]];

        foreach ($lines as $i => $n) {
            // Spread lines between y=70 (defence) and y=16 (attack).
            $y = $count === 1 ? 45 : (int) round(70 - $i * (54 / ($count - 1)));
            $role = match (true) {
                $i === 0 && $count > 1 => 'defence',
                $i === $count - 1 => 'attack',
                $count >= 4 && $i === 1 => 'holding',
                $count >= 4 && $i === $count - 2 => 'attacking',
                default => 'midfield',
            };
            foreach (self::codes($role, $n) as $j => $code) {
                $x = (int) round(($j + 1) * 100 / ($n + 1));
                $positions[] = [$code, max(12, min(88, $x)), $y];
            }
        }

        return $positions;
    }

    private static function codes(string $role, int $n): array
    {
        $centre = ['defence' => 'CB', 'holding' => 'DM', 'midfield' => 'CM', 'attacking' => 'AM', 'attack' => 'CF'][$role];
        $wide = ['defence' => ['LB', 'RB'], 'holding' => ['DM', 'DM'], 'midfield' => ['LM', 'RM'], 'attacking' => ['LW', 'RW'], 'attack' => ['LW', 'RW']][$role];

        if ($n <= 2) {
            return array_fill(0, $n, $centre);
        }

        return [$wide[0], ...array_fill(0, $n - 2, $centre), $wide[1]];
    }
}
