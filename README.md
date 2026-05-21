# ♟ Reversi Multiplayer

Browser-based multiplayer Reversi/Othello game. PHP backend, vanilla JS frontend, JSON file-based storage. Dark theme, customizable piece colors, AI opponent, room management.

---

## Screenshots

The game features a dark-themed, 8×8 Reversi board with custom piece colors, real-time status updates, and integrated chat.

---

## Features

### Lobby
- List rooms (open + in progress)
- Create room with customizable settings
- Join by room ID
- Join active game as a spectator
- Return to your own active room
- Auto-delete expired rooms

### Game
- 2-player human mode (over the network)
- Player vs Machine mode (AI)
- Real-time 1-second polling
- Room chat (except in AI mode)
- Per-turn countdown timer (optional)
- Auto-pass if no valid moves available
- Automatic end-game detection

### Customization (upon room creation)
- **Room name** — display name in the lobby
- **Custom ID** — custom URL identifier (alphanumeric)
- **Time limit** — Off / 30s / 60s / 120s / Custom (5–600s)
- **Piece colors** — 12 predefined colors, separate for each player, duplicates restricted
- **Disable spectators** — spectator joining can be disabled
- **AI difficulty** — Easy / Medium / Hard / Expert (AI mode only)

### AI Opponent
| Level | Algorithm | Depth | Endgame |
|-------|-----------|---------|----------|
| Easy | 60% random + greedy | 1 | None |
| Medium | Negamax | 2 | None |
| Hard | Negamax + alpha-beta | 4 | ≤14 empty |
| Expert | Negamax + alpha-beta | 6 | ≤18 empty |

The AI evaluation function: positional weight matrix (corners=120, X-squares=−20) + mobility bonus.

### Security
- CSRF token on all POST requests
- Path traversal protection on IDs
- Input validation (ID, move, name)
- Rate limiting: 30 requests / 10 seconds per session
- File locking (flock) against concurrent writes
- Spectators cannot make moves or chat

---

## System Requirements

### PHP
- **Version: PHP 8.0+** (recommended: 8.2 or 8.3)
- Required extensions:
  - `mbstring` — UTF-8 text handling
  - `openssl` — CSRF token generation (`random_bytes`)
  - `session` — player identification (usually built-in)
  - `json` — game state storage (usually built-in)
- Optional: `pcre` (built-in in most PHP installations)

### Server
- Any web server capable of running PHP:
  - Apache (mod_php or PHP-FPM)
  - Nginx + PHP-FPM
  - PHP built-in server (for development)
- **Not required**: Database, Node.js, Composer, npm
- **File system**: Write permissions for the `games/` directory

### Browser
- Modern browser (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)
- JavaScript enabled
- No plugins or extensions required

---

## Installation

### 1. Copying Files

```bash
git clone https://nexnet.hu:5678/peter/reversi.git
cd reversi
```

Alternatively, download as a ZIP and extract it to your web server's root directory.

### 2. Creating the `games/` Directory

```bash
mkdir games
chmod 777 games    # Linux/Mac: write permissions required
```

On Windows, simply creating the folder is sufficient.

### 3. PHP Configuration

Ensure the following extensions are enabled in your `php.ini`:

```ini
extension=mbstring
extension=openssl
```

### 4a. PHP Built-in Server (Development/Testing)

**Windows (using start.bat):**
```
start.bat
```
The browser will open automatically: `http://localhost:8000`

**Manually:**
```bash
php -S localhost:8000 -t .
```

**Portable PHP (included, Windows):**
```bash
php\php.exe -c php\php.ini -S localhost:8000 -t .
```

### 4b. Apache Configuration

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/reversi
    ServerName reversi.example.com

    <Directory /var/www/reversi>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

A `.htaccess` file in the root is required (optional, for URL rewriting):
```apache
Options -Indexes
```

### 4c. Nginx Configuration

```nginx
server {
    listen 80;
    server_name reversi.example.com;
    root /var/www/reversi;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## File Structure

```
reversi/
├── index.php          # Lobby (room list, creation, joining)
├── game.php           # Game room HTML skeleton
├── api.php            # Polling endpoint (move, chat, delete, join)
├── lib.php            # Shared helper functions (validation, CSRF, rate-limit, game logic)
├── lib_ai.php         # AI logic (negamax + alpha-beta)
├── reversi.js         # Client: polling, render, board diff, chat, timer
├── reversi.css        # Game view styles (dark theme)
├── lobby.css          # Lobby styles (dark theme)
├── start.bat          # Windows: one-click server start
├── start_server.ps1   # PowerShell: server start
├── MIGRATIONS.md      # Game state schema changelog
├── games/             # Game JSON files (gitignored)
│   └── {id}.json      # One file per game
├── js/
│   ├── bootstrap.min.css   # Bootstrap 5 (vendored)
│   └── bootstrap.min.js
└── php/               # Portable PHP (Windows, gitignored)
```

---

## Game State Schema

Every active game is stored in a JSON file (`games/{id}.json`):

```json
{
  "creator": "Peter",
  "players": ["Peter", "Bob"],
  "turn": 0,
  "board": [[0,0,...], ...],
  "finished": false,
  "chat": [{"who": "Peter", "text": "Hi!", "ts": 1700000000}],
  "spectators": ["Spectator1"],
  "timer": 60,
  "turnStartedAt": 1700000000,
  "ai": false,
  "ai_difficulty": "hard",
  "piece_colors": ["#111111", "#eeeeee"],
  "allow_spectators": true,
  "room_name": "Friends Room"
}
```

- `turn`: -1 (waiting), 0 (black moves), 1 (white moves)
- `board`: 8×8 matrix, 0=empty, 1=black, 2=white
- `timer`: 0=no time limit, otherwise seconds
- `ai_difficulty`: `"easy"` | `"medium"` | `"hard"` | `"expert"`

---

## Automatic Cleanup

The `games/` directory manages itself — no cron job required:

| State | Deletion Time |
|---------|-------------|
| Finished game | After 5 minutes |
| Waiting (1 player) | After 30 minutes |
| Abandoned (2 players, inactive) | After 2 hours |

Cleanup runs on every lobby page load.

---

## Game Rules

Standard Othello/Reversi:
- Black (Player 1) moves first.
- A move is valid if it flanks at least one opponent's piece.
- If a player has no valid moves, they automatically pass.
- If neither player has valid moves, the game ends.
- The winner is the player with the most pieces on the board.

---

## Development Notes

- No framework, no build steps — pure PHP + vanilla JS
- `flock()` locks JSON files against concurrent writes
- AI moves are lazily evaluated: executed on every poll if it's the AI's turn (with a minimum 2-second thinking time)
- The client polls the state every 1 second; stops when the game ends
- All UI text is in Hungarian
- CSRF tokens are generated per session and validated on all POST requests

---

## Known Limitations (Alpha)

- Name collision: two different users can use the same name
- JSON file storage: may slow down under heavy load (~50+ concurrent games)
- No WebSocket/SSE: 1-second polling latency
- No ELO / statistics system
- No account system / login

---

## Planned Features

- SQLite/MySQL migration
- WebSocket real-time communication
- User accounts and ELO ranking
- Replay / move log
- Mobile-friendly UI
- Sound effects

---

## License

Personal / educational project. Contact the author before commercial use.
```
