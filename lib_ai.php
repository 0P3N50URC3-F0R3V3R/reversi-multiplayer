<?php

/*
 * AI opponent — negamax with alpha-beta pruning, depth 4 (endgame: full solve).
 * Uses lib.php's flips(), validMove(), applyMove(), hasAnyMove().
 * Evaluation: positional weights + mobility bonus.
 */

/* Positional weight matrix */
function ai_weights(): array {
    return [
        [120, -20, 20,  5,  5, 20, -20, 120],
        [-20, -40, -5, -5, -5, -5, -40, -20],
        [ 20,  -5, 15,  3,  3, 15,  -5,  20],
        [  5,  -5,  3,  3,  3,  3,  -5,   5],
        [  5,  -5,  3,  3,  3,  3,  -5,   5],
        [ 20,  -5, 15,  3,  3, 15,  -5,  20],
        [-20, -40, -5, -5, -5, -5, -40, -20],
        [120, -20, 20,  5,  5, 20, -20, 120],
    ];
}

/* All valid moves for $player on board $b */
function ai_moves(array $b, int $player): array {
    $moves = [];
    for ($y = 0; $y < 8; $y++)
        for ($x = 0; $x < 8; $x++)
            if ($b[$y][$x] === 0 && count(flips($b, $x, $y, $player)) > 0)
                $moves[] = [$x, $y];
    return $moves;
}

/* Static board evaluation from $player's perspective */
function ai_eval(array $b, int $player): int {
    $opp     = $player === 1 ? 2 : 1;
    $weights = ai_weights();
    $score   = 0;

    /* Positional score */
    for ($y = 0; $y < 8; $y++)
        for ($x = 0; $x < 8; $x++) {
            if ($b[$y][$x] === $player)      $score += $weights[$y][$x];
            elseif ($b[$y][$x] === $opp)     $score -= $weights[$y][$x];
        }

    /* Mobility bonus: having more moves than opponent = control */
    $myMoves  = count(ai_moves($b, $player));
    $oppMoves = count(ai_moves($b, $opp));
    if ($myMoves + $oppMoves > 0)
        $score += 10 * ($myMoves - $oppMoves);

    return $score;
}

/* Count total disks on board */
function ai_disk_count(array $b): int {
    $n = 0;
    for ($y = 0; $y < 8; $y++)
        for ($x = 0; $x < 8; $x++)
            if ($b[$y][$x] !== 0) $n++;
    return $n;
}

/*
 * Negamax with alpha-beta.
 * Returns score from $player's perspective.
 * $depth: plies remaining. When 0 → static eval.
 */
function ai_negamax(array $b, int $player, int $depth, int $alpha, int $beta): int {
    $opp   = $player === 1 ? 2 : 1;
    $moves = ai_moves($b, $player);

    if (empty($moves)) {
        /* No moves: pass to opponent or game over */
        $oppMoves = ai_moves($b, $opp);
        if (empty($oppMoves)) {
            /* Terminal: count disks */
            $mine = 0; $theirs = 0;
            for ($y = 0; $y < 8; $y++)
                for ($x = 0; $x < 8; $x++) {
                    if ($b[$y][$x] === $player)  $mine++;
                    elseif ($b[$y][$x] === $opp) $theirs++;
                }
            return ($mine - $theirs) * 1000;
        }
        /* Pass: opponent plays, negate */
        return -ai_negamax($b, $opp, $depth, -$beta, -$alpha);
    }

    if ($depth === 0) return ai_eval($b, $player);

    /* Move ordering: sort by positional weight descending for better pruning */
    $weights = ai_weights();
    usort($moves, fn($a,$b_) => $weights[$b_[1]][$b_[0]] - $weights[$a[1]][$a[0]]);

    $best = PHP_INT_MIN;
    foreach ($moves as [$x, $y]) {
        $nb = $b;
        applyMove($nb, $x, $y, $player);
        $score = -ai_negamax($nb, $opp, $depth - 1, -$beta, -$alpha);
        if ($score > $best) $best = $score;
        if ($score > $alpha) $alpha = $score;
        if ($alpha >= $beta) break; // alpha-beta cutoff
    }
    return $best;
}

/*
 * Difficulty → search depth mapping.
 * 'easy'   : depth 1, 60% random move
 * 'medium' : depth 2
 * 'hard'   : depth 4  (default)
 * 'expert' : depth 6, deeper endgame solve
 */
function ai_pick_move(array $board, int $player, string $difficulty = 'hard'): ?array {
    $moves = ai_moves($board, $player);
    if (empty($moves)) return null;
    if (count($moves) === 1) return $moves[0];

    /* Easy: mostly random, occasionally greedy depth-1 */
    if ($difficulty === 'easy') {
        if (count($moves) > 1 && (mt_rand(0, 9) < 6)) {
            return $moves[array_rand($moves)];
        }
    }

    $disks = ai_disk_count($board);
    $baseDepth = match ($difficulty) {
        'easy'   => 1,
        'medium' => 2,
        'expert' => 6,
        default  => 4,  // hard
    };
    /* Endgame exact solve: expert at ≤18 empty, others at ≤14 */
    $endgameThreshold = ($difficulty === 'expert') ? 46 : 50;
    $depth = ($disks >= $endgameThreshold) ? (64 - $disks) : $baseDepth;

    $opp       = $player === 1 ? 2 : 1;
    $best      = null;
    $bestScore = PHP_INT_MIN;

    $weights = ai_weights();
    usort($moves, fn($a,$b_) => $weights[$b_[1]][$b_[0]] - $weights[$a[1]][$a[0]]);

    foreach ($moves as [$x, $y]) {
        $nb = $board;
        applyMove($nb, $x, $y, $player);
        $score = -ai_negamax($nb, $opp, $depth - 1, PHP_INT_MIN + 1, PHP_INT_MAX);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best      = [$x, $y];
        }
    }
    return $best;
}
