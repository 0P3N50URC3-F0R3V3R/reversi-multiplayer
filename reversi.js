let csrf = CSRF_INIT;
let lastChatTs = 0;
let countdownInterval = null;

async function fetchState(move = null, extraFields = {}) {
    const f = new FormData();
    f.append("id",   GAME_ID);
    f.append("csrf", csrf);
    if (move) {
        f.append("move[]", move[0]);
        f.append("move[]", move[1]);
    }
    for (const [k, v] of Object.entries(extraFields)) {
        f.append(k, v);
    }
    const r = await fetch("api.php", { method: "POST", body: f });
    const data = await r.json();
    if (data.csrf) csrf = data.csrf; // refresh token from server
    return data;
}

function setupDeleteBtn(game, me) {
    const delBtn = document.getElementById("deleteBtn");
    if (game.creator === game.players[me]) {
        delBtn.style.display = "inline-block";
        delBtn.onclick = async () => {
            if (confirm("Biztos törlöd a játékot?")) {
                const f = new FormData();
                f.append("id",     GAME_ID);
                f.append("csrf",   csrf);
                f.append("delete", 1);
                await fetch("api.php", { method: "POST", body: f });
                location.href = "index.php";
            }
        };
    } else {
        delBtn.style.display = "none";
    }
}

function renderChat(chatArr, isSpectator) {
    const container = document.getElementById("chat-messages");
    const inputWrap  = document.getElementById("chat-input-wrap");

    if (isSpectator) {
        if (inputWrap) inputWrap.style.display = "none";
    }

    const newMessages = chatArr.filter(m => m.ts > lastChatTs);
    if (newMessages.length === 0) return;

    newMessages.forEach(m => {
        const line = document.createElement("div");
        const who = m.who === 'system'
            ? '<em class="text-muted">' + m.text + '</em>'
            : '<b>' + escapeHtml(m.who) + '</b>: ' + escapeHtml(m.text);
        line.innerHTML = who;
        container.appendChild(line);
        lastChatTs = Math.max(lastChatTs, m.ts);
    });
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function startCountdown(turnStartedAt, timer, serverTime) {
    if (countdownInterval) clearInterval(countdownInterval);
    const timerEl = document.getElementById("countdown");
    if (!timerEl) return;

    if (!timer || timer === 0 || !turnStartedAt) {
        timerEl.textContent = "—";
        return;
    }

    /* elapsed on server at poll time + client time since poll */
    const serverElapsed   = serverTime - turnStartedAt;
    const clientReceiveMs = Date.now();

    function tick() {
        const clientElapsed = (Date.now() - clientReceiveMs) / 1000;
        const remaining     = timer - serverElapsed - clientElapsed;
        const disp = Math.max(0, Math.floor(remaining));
        timerEl.textContent = disp + "s";
        if (disp === 0) clearInterval(countdownInterval);
    }

    tick();
    countdownInterval = setInterval(tick, 500);
}

function render(data) {
    if (data.error) {
        if (data.error === 'no game') {
            document.getElementById("status").textContent = "A játék nem található vagy törölve lett.";
        }
        return;
    }

    const { game, validMoves, me, serverTime } = data;
    const boardEl  = document.getElementById("board");
    const statusEl = document.getElementById("status");
    const isSpectator = (me === -1);

    /* ===== waiting for 2nd player ===== */
    if (game.turn === -1) {
        statusEl.innerHTML = "⏳ Várakozás másik játékosra...";
        boardEl.innerHTML  = "";
        setupDeleteBtn(game, me);
        return;
    }

    boardEl.innerHTML = "";

    const validSet = new Set((validMoves || []).map(v => v.join(",")));

    let black = 0, white = 0;

    game.board.forEach((row, y) => {
        row.forEach((c, x) => {
            const cell = document.createElement("div");
            cell.className = "cell";

            if (c !== 0) {
                const disk = document.createElement("div");
                disk.className = "disk " + (c === 1 ? "black" : "white") + " flip";
                cell.appendChild(disk);
                c === 1 ? black++ : white++;
            } else if (
                !isSpectator &&
                game.turn === me &&
                validSet.has(x + "," + y)
            ) {
                cell.classList.add("valid");
                cell.onclick = () => fetchState([x, y]).then(render);
            }

            boardEl.appendChild(cell);
        });
    });

    /* ===== status bar ===== */
    const p0 = escapeHtml(game.players[0] ?? "?");
    const p1 = escapeHtml(game.players[1] ?? "várakozás...");
    let status = `<b>⚫ ${p0}</b> vs <b>⚪ ${p1}</b><br>⚫ ${black} – ⚪ ${white}<br>`;

    if (game.timer > 0) {
        status += `⏱ <span id="countdown">—</span><br>`;
    }

    if (game.finished) {
        if (black > white)      status += "⚫ Nyert!";
        else if (white > black) status += "⚪ Nyert!";
        else                    status += "Döntetlen";
    } else if (isSpectator) {
        const turnName = escapeHtml(game.players[game.turn] ?? "?");
        status += `👁 Néző mód — ${turnName} lép`;
    } else if (game.turn === me) {
        status += "🟢 A te köröd";
    } else {
        status += "🔴 Ellenfél köre";
    }

    statusEl.innerHTML = status;

    if (game.timer > 0) {
        startCountdown(game.turnStartedAt, game.timer, serverTime);
    }

    setupDeleteBtn(game, isSpectator ? -1 : me);
    renderChat(game.chat || [], isSpectator);
}

async function loop() {
    try {
        const data = await fetchState();
        render(data);
    } catch (e) {
        console.error(e);
    }
    setTimeout(loop, 1000);
}

/* Chat send */
document.addEventListener("DOMContentLoaded", () => {
    const sendBtn  = document.getElementById("chat-send");
    const chatInput = document.getElementById("chat-input");

    if (sendBtn && chatInput) {
        const doSend = async () => {
            const text = chatInput.value.trim();
            if (!text) return;
            chatInput.value = "";
            const data = await fetchState(null, { chat: text });
            render(data);
        };
        sendBtn.addEventListener("click", doSend);
        chatInput.addEventListener("keydown", e => {
            if (e.key === "Enter") doSend();
        });
    }
});

loop();
