<?php
session_start();
header('Content-Type: application/json');
require 'lib.php';

/* ===== SECURITY GATES ===== */
csrf_check();
rate_limit_check();

/* ===== VALIDATE ID ===== */
$id = $_POST['id'] ?? '';
if (!valid_game_id($id)) {
    http_response_code(400);
    exit(json_encode(['error' => 'bad id']));
}

$file = "games/$id.json";
if (!file_exists($file)) exit(json_encode(['error' => 'no game']));

$fp = fopen($file, 'c+');
if (!$fp) exit(json_encode(['error' => 'file error']));

/* ===== LOCK ===== */
flock($fp, LOCK_EX);

/* ===== LOAD ===== */
rewind($fp);
$data = stream_get_contents($fp);
$game = json_decode($data, true);

/* ===== SCHEMA DEFAULTS (backward compat with old game files) ===== */
$game += [
    'chat'         => [],
    'spectators'   => [],
    'timer'        => 0,
    'turnStartedAt'=> null,
    'ai'           => false,
];

$name = $_SESSION['name'] ?? '';

/* ===== DELETE ===== */
if (isset($_POST['delete'])) {
    if ($game['creator'] === $name) {
        flock($fp, LOCK_UN);
        fclose($fp);
        unlink($file);
        exit(json_encode(['deleted' => true]));
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(json_encode(['error' => 'not allowed']));
}

/* ===== CHAT ===== */
if (isset($_POST['chat'])) {
    if (!in_array($name, $game['players'])) {
        flock($fp, LOCK_UN);
        fclose($fp);
        exit(json_encode(['error' => 'spectators cannot chat']));
    }
    $text = trim($_POST['chat']);
    if ($text !== '') {
        $text = mb_substr($text, 0, 200);
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $game['chat'][] = ['who' => $name, 'text' => $text, 'ts' => time()];
    }
}

/* ===== JOIN ===== */
if (!in_array($name, $game['players'])) {
    /* Spectator branch */
    if (count($game['players']) >= 2) {
        if (!in_array($name, $game['spectators'])) {
            $game['spectators'][] = $name;
        }
        /* save spectator list, then respond as spectator */
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($game));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        exit(json_encode([
            'game'       => $game,
            'me'         => -1,
            'validMoves' => [],
            'serverTime' => time(),
            'csrf'       => csrf_token(),
        ]));
    }

    $game['players'][] = $name;

    if (count($game['players']) === 2) {
        $game['turn']          = 0; // ⚫ starts
        $game['turnStartedAt'] = time();
    }
}

$playerIndex = array_search($name, $game['players']);

/* ===== TIMER AUTO-PASS ===== */
if (
    $game['timer'] > 0 &&
    $game['turnStartedAt'] !== null &&
    !$game['finished'] &&
    count($game['players']) === 2 &&
    (time() - $game['turnStartedAt']) > $game['timer']
) {
    $timedOutName = $game['players'][$game['turn']];
    $game['chat'][] = [
        'who'  => 'system',
        'text' => "⏱ $timedOutName kifutott az időből",
        'ts'   => time(),
    ];
    $game['turn'] = 1 - $game['turn'];
    $game['turnStartedAt'] = time();

    /* re-run pass/game-over after auto-pass */
    $nextPlayer = $game['turn'] + 1;
    $curPlayer  = (2 - $game['turn']); // opponent of the new turn
    if (!hasAnyMove($game['board'], $nextPlayer)) {
        if (hasAnyMove($game['board'], $curPlayer)) {
            $game['turn'] = 1 - $game['turn'];
            $game['turnStartedAt'] = time();
        }
    }
    if (
        !hasAnyMove($game['board'], 1) &&
        !hasAnyMove($game['board'], 2)
    ) {
        $game['finished'] = true;
    }
}

/* ===== MOVE ===== */
if (
    isset($_POST['move']) &&
    !$game['finished'] &&
    $game['turn'] === $playerIndex
) {
    $mv = valid_move($_POST['move']);
    if ($mv !== null) {
        [$x, $y] = $mv;
        $me = $playerIndex + 1;

        if (validMove($game['board'], $x, $y, $me)) {
            applyMove($game['board'], $x, $y, $me);
            $game['turn']          = 1 - $game['turn'];
            $game['turnStartedAt'] = time();

            /* PASS logic */
            $nextPlayer = $game['turn'] + 1;
            if (!hasAnyMove($game['board'], $nextPlayer)) {
                if (hasAnyMove($game['board'], $me)) {
                    $game['turn']          = $playerIndex;
                    $game['turnStartedAt'] = time();
                }
            }
        }
    }
}

/* ===== AI MOVE (lazy: evaluated on every poll when it's AI turn) ===== */
if (
    !empty($game['ai']) &&
    $game['turn'] === 1 &&
    !$game['finished'] &&
    count($game['players']) === 2
) {
    require_once 'lib_ai.php';
    $aiMove = ai_pick_move($game['board'], 2);
    if ($aiMove !== null) {
        [$ax, $ay] = $aiMove;
        applyMove($game['board'], $ax, $ay, 2);
        $game['turn']          = 0;
        $game['turnStartedAt'] = time();

        /* PASS logic after AI */
        if (!hasAnyMove($game['board'], 1)) {
            if (hasAnyMove($game['board'], 2)) {
                $game['turn']          = 1;
                $game['turnStartedAt'] = time();
            }
        }
    }
}

/* ===== GAME OVER ===== */
if (
    count($game['players']) === 2 &&
    !hasAnyMove($game['board'], 1) &&
    !hasAnyMove($game['board'], 2)
) {
    $game['finished'] = true;
}

/* ===== VALID MOVES ===== */
$validMoves = [];
if (
    !$game['finished'] &&
    $game['turn'] === $playerIndex &&
    count($game['players']) === 2
) {
    $me = $playerIndex + 1;
    for ($y = 0; $y < 8; $y++)
        for ($x = 0; $x < 8; $x++)
            if (validMove($game['board'], $x, $y, $me))
                $validMoves[] = [$x, $y];
}

/* ===== SAVE ===== */
rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($game));
fflush($fp);

/* ===== UNLOCK ===== */
flock($fp, LOCK_UN);
fclose($fp);

/* ===== RESPONSE ===== */
echo json_encode([
    'game'       => $game,
    'me'         => $playerIndex,
    'validMoves' => $validMoves,
    'serverTime' => time(),
    'csrf'       => csrf_token(),
]);

/* ===== GAME LOGIC ===== */

function validMove($b, $x, $y, $p) {
    if ($b[$y][$x] !== 0) return false;
    return count(flips($b, $x, $y, $p)) > 0;
}

function applyMove(&$b, $x, $y, $p) {
    $b[$y][$x] = $p;
    foreach (flips($b, $x, $y, $p) as [$fx, $fy])
        $b[$fy][$fx] = $p;
}

function flips($b, $x, $y, $p) {
    $o = $p == 1 ? 2 : 1;
    $res = [];
    for ($dx = -1; $dx <= 1; $dx++)
    for ($dy = -1; $dy <= 1; $dy++) {
        if (!$dx && !$dy) continue;
        $cx = $x + $dx;
        $cy = $y + $dy;
        $tmp = [];
        while ($cx >= 0 && $cy >= 0 && $cx < 8 && $cy < 8 && $b[$cy][$cx] == $o) {
            $tmp[] = [$cx, $cy];
            $cx += $dx;
            $cy += $dy;
        }
        if ($cx >= 0 && $cy >= 0 && $cx < 8 && $cy < 8 && $b[$cy][$cx] == $p)
            $res = array_merge($res, $tmp);
    }
    return $res;
}

function hasAnyMove($b, $p) {
    for ($y = 0; $y < 8; $y++)
    for ($x = 0; $x < 8; $x++)
        if (validMove($b, $x, $y, $p)) return true;
    return false;
}
