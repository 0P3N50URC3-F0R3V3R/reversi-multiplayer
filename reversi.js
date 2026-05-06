let csrf = CSRF_INIT;
let lastChatTs = 0;
let countdownInterval = null;
let moveInFlight = false;
let gameFinished = false;

/* ===== API ===== */
async function fetchState(move = null, extra = {}) {
    const f = new FormData();
    f.append('id',   GAME_ID);
    f.append('csrf', csrf);
    if (move) { f.append('move[]', move[0]); f.append('move[]', move[1]); }
    for (const [k, v] of Object.entries(extra)) f.append(k, v);
    const r = await fetch('api.php', { method: 'POST', body: f });
    const d = await r.json();
    if (d.csrf) csrf = d.csrf;
    return d;
}

/* ===== COPY ROOM ID ===== */
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('copy-room-btn');
    if (btn) {
        btn.addEventListener('click', () => {
            navigator.clipboard.writeText(FULL_ID).then(() => {
                btn.classList.add('copied');
                setTimeout(() => btn.classList.remove('copied'), 1800);
            });
        });
    }
});

/* ===== DELETE BUTTON ===== */
function setupDeleteBtn(game, me) {
    const bar = document.getElementById('action-bar');
    if (!bar) return;

    const myName = game.players[me] ?? '';
    const isCreator = myName !== '' && game.creator === myName;

    let delBtn = document.getElementById('delete-btn');
    if (isCreator) {
        if (!delBtn) {
            delBtn = document.createElement('button');
            delBtn.id = 'delete-btn';
            delBtn.className = 'btn btn-outline-danger btn-sm';
            delBtn.textContent = '🗑 Játék törlése';
            delBtn.onclick = async () => {
                if (!confirm('Biztos törlöd a játékot?')) return;
                const f = new FormData();
                f.append('id', GAME_ID); f.append('csrf', csrf); f.append('delete', 1);
                await fetch('api.php', { method: 'POST', body: f });
                location.href = 'index.php';
            };
            bar.appendChild(delBtn);
        }
    } else {
        delBtn?.remove();
    }
}

/* ===== STATUS ===== */
function renderStatus(game, me, serverTime) {
    const isSpectator = (me === -1);
    const p0 = escHtml(game.players[0] ?? '?');
    const p1 = escHtml(game.players[1] ?? '…');
    let black = 0, white = 0;
    game.board.forEach(row => row.forEach(c => { if (c === 1) black++; else if (c === 2) white++; }));

    const turn = game.turn; // 0=black, 1=white

    const playersEl = document.getElementById('status-players');
    const turnEl    = document.getElementById('status-turn');

    playersEl.innerHTML = `
        <div class="player-block ${turn === 0 && !game.finished ? 'active' : ''}">
            <div class="player-disk-mini black"></div>
            <div class="player-name">${p0}</div>
            <div class="player-score">${black}</div>
        </div>
        <div class="score-vs">vs</div>
        <div class="player-block ${turn === 1 && !game.finished ? 'active' : ''}">
            <div class="player-disk-mini white"></div>
            <div class="player-name">${p1}</div>
            <div class="player-score">${white}</div>
        </div>`;

    if (game.finished) {
        const msg = black > white ? `⚫ ${p0} nyert!`
                  : white > black ? `⚪ ${p1} nyert!`
                  : 'Döntetlen! 🤝';
        turnEl.innerHTML = `<span class="turn-done">${msg}</span>`;
        gameFinished = true;
        if (countdownInterval) clearInterval(countdownInterval);
    } else if (game.turn === -1) {
        turnEl.innerHTML = `<span class="turn-wait">⏳ Várakozás másik játékosra…</span>`;
    } else if (isSpectator) {
        const who = turn === 0 ? `⚫ ${p0}` : `⚪ ${p1}`;
        turnEl.innerHTML = `<span class="turn-spectate">👁 ${who} lép</span>`;
    } else if (turn === me) {
        turnEl.innerHTML = `<span class="turn-you">🟢 A te köröd</span>`;
    } else {
        turnEl.innerHTML = `<span class="turn-opp">🔴 Ellenfél köre</span>`;
    }

    if (game.timer > 0 && !game.finished && game.turn !== -1) {
        let timerRow = document.getElementById('timer-row');
        if (!timerRow) {
            timerRow = document.createElement('div');
            timerRow.id = 'timer-row';
            timerRow.className = 'timer-row';
            timerRow.innerHTML = '⏱ <span id="countdown">—</span>';
            document.getElementById('status-turn').after(timerRow);
        }
        startCountdown(game.turnStartedAt, game.timer, serverTime);
    } else {
        document.getElementById('timer-row')?.remove();
        if (countdownInterval) clearInterval(countdownInterval);
    }
}

function startCountdown(turnStartedAt, timer, serverTime) {
    if (countdownInterval) clearInterval(countdownInterval);
    const el = document.getElementById('countdown');
    if (!el || !turnStartedAt) return;

    const serverElapsed   = serverTime - turnStartedAt;
    const clientReceiveMs = Date.now();

    function tick() {
        const clientElapsed = (Date.now() - clientReceiveMs) / 1000;
        const remaining = timer - serverElapsed - clientElapsed;
        const disp = Math.max(0, Math.floor(remaining));
        el.textContent = disp + 's';
        const row = document.getElementById('timer-row');
        if (row) row.className = 'timer-row' + (disp <= 10 ? ' timer-urgent' : '');
        if (disp === 0) clearInterval(countdownInterval);
    }
    tick();
    countdownInterval = setInterval(tick, 500);
}

/* ===== CHAT ===== */
function renderChat(chatArr, isSpectator) {
    const box  = document.getElementById('chat-messages');
    const wrap = document.getElementById('chat-input-wrap');
    if (!box) return;

    if (isSpectator && wrap) wrap.style.display = 'none';

    const newMsgs = chatArr.filter(m => m.ts > lastChatTs);
    if (!newMsgs.length) return;

    newMsgs.forEach(m => {
        const div = document.createElement('div');
        if (m.who === 'system') {
            div.className = 'chat-msg system';
            div.textContent = m.text;
        } else {
            div.className = 'chat-msg';
            div.innerHTML = `<b>${escHtml(m.who)}</b>: ${escHtml(m.text)}`;
        }
        box.appendChild(div);
        lastChatTs = Math.max(lastChatTs, m.ts);
    });
    box.scrollTop = box.scrollHeight;
}

function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ===== BOARD (diff-based — no re-animation on unchanged cells) ===== */
let cellEls  = null;   // cellEls[y][x] = <div class="cell">
let prevBoard = null;  // prevBoard[y][x] = 0|1|2

function buildBoard() {
    const boardEl = document.getElementById('board');
    boardEl.innerHTML = '';
    cellEls   = [];
    prevBoard = [];
    for (let y = 0; y < 8; y++) {
        cellEls[y]   = [];
        prevBoard[y] = [];
        for (let x = 0; x < 8; x++) {
            const cell = document.createElement('div');
            cell.className = 'cell';
            boardEl.appendChild(cell);
            cellEls[y][x]   = cell;
            prevBoard[y][x] = -1; // force first paint
        }
    }
}

function renderBoard(game, validMoves, me) {
    const isSpectator = (me === -1);
    const validSet    = new Set((validMoves || []).map(v => v.join(',')));

    if (!cellEls) buildBoard();

    for (let y = 0; y < 8; y++) {
        for (let x = 0; x < 8; x++) {
            const c    = game.board[y][x];
            const cell = cellEls[y][x];
            const wasValid = cell.classList.contains('valid');
            const isValid  = !isSpectator && game.turn === me && c === 0 && validSet.has(x + ',' + y);

            /* Update disk only when value changed */
            if (c !== prevBoard[y][x]) {
                cell.innerHTML = '';
                if (c !== 0) {
                    const disk = document.createElement('div');
                    disk.className = 'disk ' + (c === 1 ? 'black' : 'white');
                    cell.appendChild(disk);
                }
                prevBoard[y][x] = c;
            }

            /* Update valid-move highlight */
            if (isValid !== wasValid) {
                cell.classList.toggle('valid', isValid);
                cell.onclick = isValid
                    ? () => {
                        if (moveInFlight) return;
                        moveInFlight = true;
                        fetchState([x, y]).then(render).finally(() => { moveInFlight = false; });
                    }
                    : null;
            }
        }
    }
}

/* ===== RENDER ===== */
function render(data) {
    if (data.error) {
        const s = document.getElementById('status-turn');
        if (s) s.innerHTML = `<span class="turn-opp">${escHtml(data.error)}</span>`;
        return;
    }

    const { game, validMoves, me, serverTime } = data;

    renderBoard(game, validMoves, me);
    renderStatus(game, me, serverTime);
    setupDeleteBtn(game, me);
    renderChat(game.chat || [], me === -1);
}

/* ===== LOOP ===== */
async function loop() {
    try {
        const data = await fetchState();
        render(data);
    } catch (e) { console.error(e); }
    if (!gameFinished) setTimeout(loop, 1000);
}

/* ===== CHAT SEND ===== */
document.addEventListener('DOMContentLoaded', () => {
    const btn   = document.getElementById('chat-send');
    const input = document.getElementById('chat-input');
    if (!btn || !input) return;
    const send = async () => {
        const t = input.value.trim();
        if (!t) return;
        input.value = '';
        render(await fetchState(null, { chat: t }));
    };
    btn.addEventListener('click', send);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') send(); });
});

loop();
