<?php
session_start();
require 'lib.php';
if (!isset($_SESSION['name'])) {
    header('Location: index.php');
    exit;
}
$id = $_GET['id'] ?? '';
if (!valid_game_id($id)) {
    header('Location: index.php');
    exit;
}
$isSpectate = isset($_GET['spectate']) && $_GET['spectate'] === '1';
$shortId    = substr($id, 0, 8) . '…';
?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reversi – Szoba</title>
<link href="js/bootstrap.min.css" rel="stylesheet">
<link href="reversi.css" rel="stylesheet">
</head>
<body>

<!-- TOP NAV -->
<nav class="game-nav">
  <a href="index.php" class="back-link">← Lobby</a>
  <span class="room-id-wrap">
    <span class="room-label">Szoba:</span>
    <code id="room-id-text"><?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?></code>
    <button class="copy-btn" id="copy-room-btn" title="Azonosító másolása">📋</button>
  </span>
  <?php if ($isSpectate): ?>
  <span class="spectator-badge">👁 Néző</span>
  <?php endif; ?>
</nav>

<!-- MAIN LAYOUT -->
<div class="game-layout">

  <!-- LEFT: board -->
  <div class="board-col">
    <div id="board" class="board"></div>
  </div>

  <!-- RIGHT: status + controls + chat -->
  <div class="side-col">

    <!-- Status card -->
    <div class="status-card" id="status-card">
      <div id="status-players" class="status-players"></div>
      <div id="status-turn"    class="status-turn"></div>
    </div>

    <!-- Delete / action buttons -->
    <div id="action-bar" class="action-bar"></div>

    <!-- Chat -->
    <div class="chat-wrap">
      <div class="chat-header">💬 Chat</div>
      <div id="chat-messages" class="chat-messages"></div>
      <div id="chat-input-wrap" class="chat-input-row">
        <input id="chat-input" class="chat-input" type="text" maxlength="200" placeholder="Üzenet…">
        <button id="chat-send" class="chat-send-btn">Küld</button>
      </div>
    </div>

  </div>
</div>

<script>
const GAME_ID   = "<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>";
const CSRF_INIT = "<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>";
const IS_SPECTATE = <?= $isSpectate ? 'true' : 'false' ?>;
const FULL_ID   = "<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>";
</script>
<script src="reversi.js"></script>
</body>
</html>
