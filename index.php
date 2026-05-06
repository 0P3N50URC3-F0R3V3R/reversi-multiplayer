<?php
session_start();
require 'lib.php';

$error   = '';
$success = '';

function initBoard() {
    $b = array_fill(0, 8, array_fill(0, 8, 0));
    $b[3][3] = $b[4][4] = 2;
    $b[3][4] = $b[4][3] = 1;
    return $b;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        $error = 'Érvénytelen kérés (CSRF).';
    } else {

        /* ===== SET NAME ===== */
        if (isset($_POST['set_name'])) {
            $n = valid_name($_POST['name'] ?? '');
            if ($n === null) { $error = 'Név kötelező (max 32 karakter).'; }
            else             { $_SESSION['name'] = $n; }
        }

        /* ===== CREATE / CREATE AI ===== */
        elseif (isset($_POST['create']) || isset($_POST['create_ai'])) {
            $n = valid_name($_POST['name'] ?? '');
            if ($n === null) { $error = 'Név kötelező (max 32 karakter).'; }
            else {
                $_SESSION['name'] = $n;
                $ai = isset($_POST['create_ai']);

                $customId = trim($_POST['custom_id'] ?? '');
                if ($customId !== '') {
                    if (!valid_game_id($customId))           { $error = 'Érvénytelen azonosító.'; }
                    elseif (file_exists("games/$customId.json")) { $error = 'Ez az azonosító már foglalt.'; }
                    else                                     { $id = $customId; }
                } else {
                    $id = bin2hex(random_bytes(8));
                }

                if ($error === '') {
                    $timerRaw = $_POST['timer'] ?? '0';
                    if ($timerRaw === 'custom') {
                        $timerVal = (int)($_POST['timer_custom'] ?? 0);
                        if ($timerVal < 5 || $timerVal > 600) $timerVal = 0;
                    } else {
                        $timerVal = (int)$timerRaw;
                        if (!in_array($timerVal, [0, 30, 60, 120])) $timerVal = 0;
                    }

                    $players = $ai ? [$n, 'Gép'] : [$n];
                    $game = [
                        'creator'       => $n,
                        'players'       => $players,
                        'turn'          => $ai ? 0 : -1,
                        'board'         => initBoard(),
                        'finished'      => false,
                        'chat'          => [],
                        'spectators'    => [],
                        'timer'         => $timerVal,
                        'turnStartedAt' => $ai ? time() : null,
                        'ai'            => $ai,
                    ];
                    $path = "games/$id.json";
                    $wfp  = fopen($path, 'x');
                    if ($wfp === false) { $error = 'Ez az azonosító már foglalt.'; }
                    else {
                        fwrite($wfp, json_encode($game));
                        fclose($wfp);
                        header("Location: game.php?id=$id");
                        exit;
                    }
                }
            }
        }

        /* ===== JOIN BY ID ===== */
        elseif (isset($_POST['join'])) {
            $n = valid_name($_POST['name'] ?? '');
            if ($n === null) { $error = 'Név kötelező (max 32 karakter).'; }
            else {
                $_SESSION['name'] = $n;
                $joinId = trim($_POST['game_id'] ?? '');
                if (!valid_game_id($joinId)) { $error = 'Érvénytelen játék azonosító.'; }
                else {
                    header("Location: game.php?id=$joinId");
                    exit;
                }
            }
        }

        /* ===== DELETE ROOM FROM LOBBY ===== */
        elseif (isset($_POST['delete_room'])) {
            $delId = trim($_POST['delete_room'] ?? '');
            if (valid_game_id($delId) && ($_SESSION['name'] ?? '') !== '') {
                $delFile = "games/$delId.json";
                if (file_exists($delFile)) {
                    $raw = @file_get_contents($delFile);
                    $g   = $raw ? json_decode($raw, true) : null;
                    if (is_array($g) && $g['creator'] === $_SESSION['name']) {
                        @unlink($delFile);
                        $success = 'Szoba törölve.';
                    } else {
                        $error = 'Csak a létrehozó törölheti a szobát.';
                    }
                }
            }
        }
    }
}

cleanup_stale_games();

$myName    = $_SESSION['name'] ?? '';
$openGames = [];
$activeGames = [];
$files = glob('games/*.json') ?: [];
$shown = 0;
foreach ($files as $file) {
    if ($shown++ >= 200) break;
    $raw = @file_get_contents($file);
    if (!$raw) continue;
    $g = json_decode($raw, true);
    if (!is_array($g) || !empty($g['finished'])) continue;
    $gid = basename($file, '.json');
    $entry = [
        'id'        => $gid,
        'creator'   => htmlspecialchars($g['creator'] ?? '?', ENT_QUOTES, 'UTF-8'),
        'rawCreator'=> $g['creator'] ?? '',
        'players'   => array_map(fn($p) => htmlspecialchars($p, ENT_QUOTES, 'UTF-8'), $g['players'] ?? []),
        'ai'        => !empty($g['ai']),
        'timer'     => (int)($g['timer'] ?? 0),
    ];
    if (count($g['players']) < 2) {
        $openGames[] = $entry;
    } else {
        $activeGames[] = $entry;
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<title>Reversi – Lobby</title>
<link href="js/bootstrap.min.css" rel="stylesheet">
<style>
  #timer-custom-wrap { display:none; }
  .lobby-table td, .lobby-table th { vertical-align:middle; }
  .name-badge { font-weight:600; color:#0d6efd; }
  .timer-badge { font-size:.75em; color:#6c757d; }
</style>
</head>
<body class="container mt-4">

<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="mb-0">♟ Reversi</h2>
  <?php if ($myName !== ''): ?>
  <div>
    <span class="name-badge me-2">👤 <?= htmlspecialchars($myName, ENT_QUOTES, 'UTF-8') ?></span>
    <form method="post" class="d-inline">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="name" value="">
      <button class="btn btn-outline-secondary btn-sm" name="set_name">Névváltás</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if ($error !== ''): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- ===== NAME PROMPT (if not set) ===== -->
<?php if ($myName === ''): ?>
<div class="card mb-4 border-primary">
  <div class="card-header bg-primary text-white">Üdvözöljük! Adja meg a nevét a kezdéshez</div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <div class="col-auto flex-grow-1">
        <input class="form-control" name="name" placeholder="Neved" maxlength="32" autofocus required>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary" name="set_name">Bejelentkezés</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ===== CREATE FORM ===== -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Új játék létrehozása</span>
    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#createForm">
      Beállítások ▾
    </button>
  </div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <?php if ($myName === ''): ?>
      <div class="mb-2">
        <input class="form-control" name="name" placeholder="Neved" maxlength="32">
      </div>
      <?php else: ?>
      <input type="hidden" name="name" value="<?= htmlspecialchars($myName, ENT_QUOTES, 'UTF-8') ?>">
      <?php endif; ?>

      <div class="collapse <?= ($error !== '' ? 'show' : '') ?>" id="createForm">
        <div class="mb-2">
          <label class="form-label text-muted small">Egyéni szoba azonosító <em>(opcionális)</em></label>
          <input class="form-control" name="custom_id" placeholder="pl. baratok-szobaja" maxlength="64">
        </div>
        <div class="mb-2">
          <label class="form-label text-muted small">Körönkénti időkorlát</label>
          <select class="form-select" name="timer" id="timer-select">
            <option value="0">Ki (nincs időkorlát)</option>
            <option value="30">30 mp</option>
            <option value="60">60 mp</option>
            <option value="120">120 mp</option>
            <option value="custom">Egyéni...</option>
          </select>
        </div>
        <div class="mb-3" id="timer-custom-wrap">
          <input class="form-control" type="number" name="timer_custom" min="5" max="600" placeholder="Másodperc (5–600)" value="60">
        </div>
      </div>

      <div class="d-flex gap-2 mt-2">
        <button class="btn btn-primary" name="create">🎮 Új játék (2 játékos)</button>
        <button class="btn btn-secondary" name="create_ai">🤖 Játék gép ellen</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== JOIN BY ID ===== -->
<div class="card mb-4">
  <div class="card-header">Csatlakozás szoba azonosítóval</div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <?php if ($myName === ''): ?>
      <div class="col-sm-4">
        <input class="form-control" name="name" placeholder="Neved" maxlength="32">
      </div>
      <?php else: ?>
      <input type="hidden" name="name" value="<?= htmlspecialchars($myName, ENT_QUOTES, 'UTF-8') ?>">
      <?php endif; ?>
      <div class="col">
        <input class="form-control" name="game_id" placeholder="Szoba azonosító">
      </div>
      <div class="col-auto">
        <button class="btn btn-success" name="join">Csatlakozás</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== OPEN GAMES ===== -->
<?php if (!empty($openGames)): ?>
<h5>🟡 Nyitott szobák <small class="text-muted fw-normal">(várakoznak játékosra)</small></h5>
<table class="table table-sm table-hover lobby-table mb-4">
  <thead class="table-light"><tr><th>Szoba</th><th>Létrehozta</th><th>Időkorlát</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($openGames as $g): ?>
  <tr>
    <td><code><?= htmlspecialchars($g['id'], ENT_QUOTES, 'UTF-8') ?></code></td>
    <td><?= $g['creator'] ?></td>
    <td class="timer-badge"><?= $g['timer'] > 0 ? $g['timer'].'mp' : '—' ?></td>
    <td class="text-end">
      <?php if ($myName !== '' && $myName !== $g['rawCreator']): ?>
        <a href="game.php?id=<?= htmlspecialchars($g['id'], ENT_QUOTES, 'UTF-8') ?>"
           class="btn btn-success btn-sm">Csatlakozás</a>
      <?php elseif ($myName === ''): ?>
        <span class="text-muted small">Előbb add meg a neved</span>
      <?php else: ?>
        <span class="badge bg-secondary me-1">Saját szobád</span>
      <?php endif; ?>
      <?php if ($myName === $g['rawCreator']): ?>
        <form method="post" class="d-inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="delete_room" value="<?= htmlspecialchars($g['id'], ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Törlöd a szobát?')">🗑 Törlés</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- ===== ACTIVE GAMES ===== -->
<?php if (!empty($activeGames)): ?>
<h5>🟢 Folyamatban lévő játékok</h5>
<table class="table table-sm table-hover lobby-table mb-4">
  <thead class="table-light"><tr><th>Szoba</th><th>Játékosok</th><th>Időkorlát</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($activeGames as $g): ?>
  <?php $isPlayer = in_array($myName, array_map(fn($p) => html_entity_decode($p), $g['players'])); ?>
  <tr>
    <td><code><?= htmlspecialchars($g['id'], ENT_QUOTES, 'UTF-8') ?></code></td>
    <td><?= implode(' vs ', $g['players']) ?><?= $g['ai'] ? ' 🤖' : '' ?></td>
    <td class="timer-badge"><?= $g['timer'] > 0 ? $g['timer'].'mp' : '—' ?></td>
    <td class="text-end">
      <?php if ($isPlayer && $myName !== ''): ?>
        <a href="game.php?id=<?= htmlspecialchars($g['id'], ENT_QUOTES, 'UTF-8') ?>"
           class="btn btn-primary btn-sm">↩ Visszatérés</a>
      <?php else: ?>
        <a href="game.php?id=<?= htmlspecialchars($g['id'], ENT_QUOTES, 'UTF-8') ?>&spectate=1"
           class="btn btn-outline-secondary btn-sm">👁 Nézőként</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if (empty($openGames) && empty($activeGames)): ?>
<div class="text-center text-muted py-5">
  <div style="font-size:3rem">♟</div>
  <p>Jelenleg nincs aktív játék.<br>Hozz létre egy új szobát!</p>
</div>
<?php endif; ?>

<script src="js/bootstrap.min.js"></script>
<script>
document.getElementById('timer-select')?.addEventListener('change', function () {
    document.getElementById('timer-custom-wrap').style.display =
        this.value === 'custom' ? 'block' : 'none';
});
</script>
</body>
</html>
