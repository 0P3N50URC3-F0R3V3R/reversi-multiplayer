<?php
session_start();
require 'lib.php';

$error = '';

function initBoard() {
    $b = array_fill(0, 8, array_fill(0, 8, 0));
    $b[3][3] = $b[4][4] = 2;
    $b[3][4] = $b[4][3] = 1;
    return $b;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* CSRF check for state-mutating POSTs */
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        $error = 'Érvénytelen kérés (CSRF).';
    } else {
        $name = valid_name($_POST['name'] ?? '');
        if ($name === null) {
            $error = 'Név kötelező (max 32 karakter).';
        } else {
            $_SESSION['name'] = $name;

            if (isset($_POST['create']) || isset($_POST['create_ai'])) {
                $ai = isset($_POST['create_ai']);

                /* Custom session id */
                $customId = trim($_POST['custom_id'] ?? '');
                if ($customId !== '') {
                    if (!valid_game_id($customId)) {
                        $error = 'Érvénytelen azonosító (csak betű, szám, - és _ megengedett, 1–64 karakter).';
                    } elseif (file_exists("games/$customId.json")) {
                        $error = 'Ez az azonosító már foglalt.';
                    } else {
                        $id = $customId;
                    }
                } else {
                    $id = uniqid();
                }

                if ($error === '') {
                    /* Timer */
                    $timerRaw = $_POST['timer'] ?? '0';
                    if ($timerRaw === 'custom') {
                        $timerVal = (int)($_POST['timer_custom'] ?? 0);
                        if ($timerVal < 5 || $timerVal > 600) $timerVal = 0;
                    } else {
                        $timerVal = (int)$timerRaw;
                        if (!in_array($timerVal, [0, 30, 60, 120])) $timerVal = 0;
                    }

                    $players = $ai ? [$name, 'Gép'] : [$name];
                    $game = [
                        'creator'      => $name,
                        'players'      => $players,
                        'turn'         => $ai ? 0 : -1,
                        'board'        => initBoard(),
                        'finished'     => false,
                        'chat'         => [],
                        'spectators'   => [],
                        'timer'        => $timerVal,
                        'turnStartedAt'=> $ai ? time() : null,
                        'ai'           => $ai,
                    ];
                    file_put_contents("games/$id.json", json_encode($game));
                    header("Location: game.php?id=$id");
                    exit;
                }
            }

            if (isset($_POST['join']) && $error === '') {
                $joinId = trim($_POST['game_id'] ?? '');
                if (!valid_game_id($joinId)) {
                    $error = 'Érvénytelen játék azonosító.';
                } else {
                    header("Location: game.php?id=$joinId");
                    exit;
                }
            }
        }
    }
}

/* Cleanup stale games on each lobby load */
cleanup_stale_games();

/* Load lobby lists */
$openGames     = [];
$activeGames   = [];
$files = glob('games/*.json') ?: [];
$shown = 0;
foreach ($files as $file) {
    if ($shown++ >= 200) break;
    $raw = @file_get_contents($file);
    if ($raw === false) continue;
    $g = json_decode($raw, true);
    if (!is_array($g) || !empty($g['finished'])) continue;
    $gid = basename($file, '.json');
    $entry = [
        'id'      => $gid,
        'creator' => htmlspecialchars($g['creator'] ?? '?', ENT_QUOTES, 'UTF-8'),
        'players' => array_map(fn($p) => htmlspecialchars($p, ENT_QUOTES, 'UTF-8'), $g['players'] ?? []),
        'ai'      => !empty($g['ai']),
    ];
    if (count($g['players']) < 2) {
        $openGames[] = $entry;
    } else {
        $activeGames[] = $entry;
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Reversi – Lobby</title>
<link href="js/bootstrap.min.css" rel="stylesheet">
<style>
  .lobby-table td, .lobby-table th { vertical-align: middle; }
  #timer-custom-wrap { display: none; }
</style>
</head>
<body class="container mt-5">
<h2>Reversi Többjátékos</h2>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- ===== CREATE FORM ===== -->
<div class="card mb-4">
<div class="card-header">Új játék</div>
<div class="card-body">
<form method="post">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <div class="mb-2">
    <label class="form-label">Neved</label>
    <input class="form-control" name="name" placeholder="Neved" maxlength="32"
           value="<?= htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  </div>
  <div class="mb-2">
    <label class="form-label">Egyéni szoba azonosító <small class="text-muted">(opcionális)</small></label>
    <input class="form-control" name="custom_id" placeholder="pl. my-room-123" maxlength="64">
  </div>
  <div class="mb-2">
    <label class="form-label">Körönkénti időkorlát</label>
    <select class="form-select" name="timer" id="timer-select">
      <option value="0">Ki (nincs időkorlát)</option>
      <option value="30">30 másodperc</option>
      <option value="60">60 másodperc</option>
      <option value="120">120 másodperc</option>
      <option value="custom">Egyéni...</option>
    </select>
  </div>
  <div class="mb-3" id="timer-custom-wrap">
    <label class="form-label">Egyéni időkorlát (másodperc, 5–600)</label>
    <input class="form-control" type="number" name="timer_custom" min="5" max="600" value="60">
  </div>
  <button class="btn btn-primary" name="create">Új játék (2 játékos)</button>
  <button class="btn btn-secondary" name="create_ai">Játék gép ellen</button>
</form>
</div>
</div>

<!-- ===== JOIN FORM ===== -->
<div class="card mb-4">
<div class="card-header">Csatlakozás azonosítóval</div>
<div class="card-body">
<form method="post">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <input class="form-control mb-2" name="name" placeholder="Neved" maxlength="32"
         value="<?= htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <input class="form-control mb-2" name="game_id" placeholder="Szoba azonosító">
  <button class="btn btn-success" name="join">Csatlakozás</button>
</form>
</div>
</div>

<!-- ===== OPEN GAMES ===== -->
<?php if (!empty($openGames)): ?>
<h5>Nyitott játékok (várakoznak játékosra)</h5>
<table class="table table-sm lobby-table mb-4">
  <thead><tr><th>Szoba</th><th>Létrehozta</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($openGames as $g): ?>
  <tr>
    <td><code><?= $g['id'] ?></code></td>
    <td><?= $g['creator'] ?></td>
    <td>
      <form method="post" class="d-inline">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="name" value="<?= htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="game_id" value="<?= htmlspecialchars($g['id'], ENT_QUOTES, 'UTF-8') ?>">
        <button class="btn btn-success btn-sm" name="join">Csatlakozás</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- ===== ACTIVE GAMES ===== -->
<?php if (!empty($activeGames)): ?>
<h5>Folyamatban lévő játékok</h5>
<table class="table table-sm lobby-table mb-4">
  <thead><tr><th>Szoba</th><th>Játékosok</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($activeGames as $g): ?>
  <tr>
    <td><code><?= $g['id'] ?></code></td>
    <td><?= implode(' vs ', $g['players']) ?><?= $g['ai'] ? ' 🤖' : '' ?></td>
    <td><a href="game.php?id=<?= htmlspecialchars($g['id'], ENT_QUOTES, 'UTF-8') ?>&spectate=1"
           class="btn btn-outline-secondary btn-sm">Nézőként belép</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if (empty($openGames) && empty($activeGames)): ?>
<p class="text-muted">Jelenleg nincs aktív játék. Hozz létre egyet!</p>
<?php endif; ?>

<script>
document.getElementById('timer-select').addEventListener('change', function () {
    document.getElementById('timer-custom-wrap').style.display =
        this.value === 'custom' ? 'block' : 'none';
});
</script>
</body>
</html>
