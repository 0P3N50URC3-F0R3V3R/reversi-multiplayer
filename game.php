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
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Reversi</title>
<link href="js/bootstrap.min.css" rel="stylesheet">
<link href="reversi.css" rel="stylesheet">
</head>
<body class="container mt-3">

<h4>Szoba azonosító ID: <?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?></h4>
<div id="status" class="mb-2"></div>

<div id="board" class="board mb-3"></div>

<button id="deleteBtn" class="btn btn-danger btn-sm" style="display:none">
    Játék törlése
</button>

<!-- Chat panel -->
<div id="chat-panel" class="mt-3" style="max-width:500px">
  <div id="chat-messages" class="border rounded p-2 mb-2"
       style="height:150px;overflow-y:auto;background:#f8f9fa;font-size:.9em"></div>
  <div id="chat-input-wrap" class="input-group">
    <input id="chat-input" class="form-control" type="text" maxlength="200" placeholder="Üzenet...">
    <button id="chat-send" class="btn btn-outline-secondary">Küldés</button>
  </div>
</div>

<script>
const GAME_ID    = "<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>";
const CSRF_INIT  = "<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>";
const IS_SPECTATE = <?= $isSpectate ? 'true' : 'false' ?>;
</script>
<script src="reversi.js"></script>
</body>
</html>
