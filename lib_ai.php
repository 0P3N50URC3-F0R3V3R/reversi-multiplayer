<?php

/* Positional weight matrix for 8x8 board (from black's perspective, symmetric) */
function ai_weight_matrix(): array {
    return [
        [100, -30,  10,  5,  5, 10, -30, 100],
        [-30, -50,  -2, -2, -2, -2, -50, -30],
        [ 10,  -2,   8,  3,  3,  8,  -2,  10],
        [  5,  -2,   3,  1,  1,  3,  -2,   5],
        [  5,  -2,   3,  1,  1,  3,  -2,   5],
        [ 10,  -2,   8,  3,  3,  8,  -2,  10],
        [-30, -50,  -2, -2, -2, -2, -50, -30],
        [100, -30,  10,  5,  5, 10, -30, 100],
    ];
}

/**
 * Greedy 1-ply heuristic.
 * Score = positional weight of landing square + sum of weights of captured disks.
 * Tie-break: most disks flipped.
 * Returns [x, y] or null if no valid move.
 */
/* Uses flips() from lib.php (loaded before this file via api.php's require 'lib.php') */
function ai_pick_move(array $board, int $player): ?array {
    $weights   = ai_weight_matrix();
    $best      = null;
    $bestScore = PHP_INT_MIN;
    $bestFlips = -1;

    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            if ($board[$y][$x] !== 0) continue;
            $flipped = flips($board, $x, $y, $player);
            if (empty($flipped)) continue;

            $score = $weights[$y][$x];
            foreach ($flipped as [$fx, $fy]) {
                $score += $weights[$fy][$fx];
            }

            $flipCount = count($flipped);
            if ($score > $bestScore || ($score === $bestScore && $flipCount > $bestFlips)) {
                $bestScore = $score;
                $bestFlips = $flipCount;
                $best      = [$x, $y];
            }
        }
    }

    return $best;
}
