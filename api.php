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
if (!$fp) {
    http_response_code(500);
    exit(json_encode(['error' => 'file error']));
}

/* ===== LOCK ===== */
flock($fp, LOCK_EX);

/* ===== LOAD ===== */
rewind($fp);
$data = stream_get_contents($fp);
$game = json_decode($data, true);
if (!is_array($game)) {
    flock($fp, LOCK_UN);
    fclose($fp);
    http_response_code(500);
    exit(json_encode(['error' => 'corrupt game']));
}

/* ===== SCHEMA DEFAULTS (backward compat with old game files) ===== */
$game += [
    'chat'            => [],
    'spectators'      => [],
    'timer'           => 0,
    'turnStartedAt'   => null,
    'ai'              => false,
    'ai_difficulty'   => 'hard',
    'allow_spectators'=> true,
    'piece_colors'    => ['#111111', '#eeeeee'],
    'room_name'       => '',
];

$name = $_SESSION['name'] ?? '';
if ($name === '') {
    flock($fp, LOCK_UN);
    fclose($fp);
    http_response_code(401);
    exit(json_encode(['error' => 'login required']));
}

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
    if (!empty($game['ai'])) {
        /* AI games have no chat */
    } elseif (!in_array($name, $game['players'])) {
        flock($fp, LOCK_UN);
        fclose($fp);
        exit(json_encode(['error' => 'spectators cannot chat']));
    } else {
        $text = trim($_POST['chat']);
        if ($text !== '') {
            $text = mb_substr($text, 0, 200);
            $game['chat'][] = ['who' => $name, 'text' => $text, 'ts' => time()];
        }
    }
}

/* ===== JOIN ===== */
if (!in_array($name, $game['players'])) {
    /* Spectator branch */
    if (count($game['players']) >= 2) {
        if (empty($game['allow_spectators'])) {
            flock($fp, LOCK_UN);
            fclose($fp);
            exit(json_encode(['error' => 'spectators_disabled']));
        }
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

/* ===== AI MOVE (min 2s think delay so it feels natural) ===== */
if (
    !empty($game['ai']) &&
    $game['turn'] === 1 &&
    !$game['finished'] &&
    count($game['players']) === 2 &&
    (time() - ($game['turnStartedAt'] ?? 0)) >= 2
) {
    require_once 'lib_ai.php';
    $aiMove = ai_pick_move($game['board'], 2, $game['ai_difficulty'] ?? 'hard');
    if ($aiMove !== null) {
        [$ax, $ay] = $aiMove;
        applyMove($game['board'], $ax, $ay, 2);
        $game['turn']          = 0;
        $game['turnStartedAt'] = time();

        /* PASS: if human has no moves after AI move, give turn back to AI */
        if (!hasAnyMove($game['board'], 1)) {
            if (hasAnyMove($game['board'], 2)) {
                $game['turn']          = 1;
                $game['turnStartedAt'] = time();
            }
            /* else: neither can move → game-over block fires below */
        }
    } else {
        /* AI has no valid moves — pass to human (or game over) */
        $game['turn']          = 0;
        $game['turnStartedAt'] = time();
    }
}

/* ===== AUTO-PASS (current player has no moves but opponent does) ===== */
if (
    !$game['finished'] &&
    count($game['players']) === 2 &&
    $game['turn'] >= 0
) {
    $curBoardVal = $game['turn'] + 1;   // 1=black, 2=white
    $oppBoardVal = 3 - $curBoardVal;
    if (!hasAnyMove($game['board'], $curBoardVal) && hasAnyMove($game['board'], $oppBoardVal)) {
        $game['turn']          = 1 - $game['turn'];
        $game['turnStartedAt'] = time();
        $game['chat'][]        = [
            'who'  => 'system',
            'text' => $game['players'][$curBoardVal - 1] . ' passzol (nincs érvényes lépés)',
            'ts'   => time(),
        ];
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

/* Game logic: validMove, applyMove, flips, hasAnyMove are in lib.php */
