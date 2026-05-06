<?php

function valid_game_id(string $id): bool {
    return (bool) preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $id);
}

function valid_move($m): ?array {
    if (!is_array($m) || count($m) !== 2) return null;
    $x = filter_var($m[0], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 7]]);
    $y = filter_var($m[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 7]]);
    if ($x === false || $y === false) return null;
    return [(int)$x, (int)$y];
}

function valid_name(string $n): ?string {
    $n = trim($n);
    if ($n === '' || mb_strlen($n) > 32) return null;
    return $n;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit(json_encode(['error' => 'csrf']));
    }
}

function rate_limit_check(): void {
    $now = time();
    $window = 10;
    $max    = 30;

    if (empty($_SESSION['rl'])) {
        $_SESSION['rl'] = ['count' => 0, 'start' => $now];
    }

    if ($now - $_SESSION['rl']['start'] >= $window) {
        $_SESSION['rl'] = ['count' => 0, 'start' => $now];
    }

    $_SESSION['rl']['count']++;

    if ($_SESSION['rl']['count'] > $max) {
        http_response_code(429);
        exit(json_encode(['error' => 'rate_limit']));
    }
}

/* ===== REVERSI GAME LOGIC ===== */

function flips(array $b, int $x, int $y, int $p): array {
    $o   = $p === 1 ? 2 : 1;
    $res = [];
    for ($dx = -1; $dx <= 1; $dx++)
    for ($dy = -1; $dy <= 1; $dy++) {
        if (!$dx && !$dy) continue;
        $cx = $x + $dx; $cy = $y + $dy; $tmp = [];
        while ($cx >= 0 && $cy >= 0 && $cx < 8 && $cy < 8 && $b[$cy][$cx] === $o) {
            $tmp[] = [$cx, $cy];
            $cx += $dx; $cy += $dy;
        }
        if ($cx >= 0 && $cy >= 0 && $cx < 8 && $cy < 8 && $b[$cy][$cx] === $p)
            $res = array_merge($res, $tmp);
    }
    return $res;
}

function validMove(array $b, int $x, int $y, int $p): bool {
    if ($b[$y][$x] !== 0) return false;
    return count(flips($b, $x, $y, $p)) > 0;
}

function applyMove(array &$b, int $x, int $y, int $p): void {
    $b[$y][$x] = $p;
    foreach (flips($b, $x, $y, $p) as [$fx, $fy])
        $b[$fy][$fx] = $p;
}

function hasAnyMove(array $b, int $p): bool {
    for ($y = 0; $y < 8; $y++)
    for ($x = 0; $x < 8; $x++)
        if (validMove($b, $x, $y, $p)) return true;
    return false;
}

function cleanup_stale_games(): void {
    $now   = time();
    $files = glob('games/*.json') ?: [];
    $count = 0;
    foreach ($files as $file) {
        if ($count++ >= 200) break;
        $mtime = @filemtime($file);
        if ($mtime === false) continue;

        $raw = @file_get_contents($file);
        if ($raw === false) continue;
        $g = json_decode($raw, true);
        if (!is_array($g)) continue;

        $finished     = !empty($g['finished']);
        $playerCount  = count($g['players'] ?? []);

        $age = $now - $mtime;
        if ($finished && $age > 60) {
            @unlink($file);                          // finished → delete after 60s
        } elseif ($playerCount < 2 && $age > 1800) {
            @unlink($file);                          // waiting, nobody joined → 30 min
        } elseif ($playerCount >= 2 && !$finished && $age > 7200) {
            @unlink($file);                          // abandoned in-progress → 2 h
        }
    }
}
