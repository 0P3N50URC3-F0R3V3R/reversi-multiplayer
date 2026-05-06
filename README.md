# ♟ Reversi Többjátékos

Böngészőalapú többjátékos Reversi/Othello játék. PHP backend, vanilla JS frontend, JSON fájl alapú tárolás. Sötét téma, testreszabható korongszínek, AI ellenfél, szoba kezelés.

---

## Képernyőképek

A játék egy sötét témájú, 8×8-as Reversi táblát jelenít meg egyéni korongszínekkel, valós idejű állapottal és integrált chattel.

---

## Funkciók

### Lobby
- Szobák listázása (nyitott + folyamatban lévő)
- Szoba létrehozása testreszabható beállításokkal
- Csatlakozás szoba azonosítóval
- Nézőként való belépés aktív játékba
- Visszatérés saját aktív szobába
- Lejárt szobák automatikus törlése

### Játék
- 2 játékos emberi mód (ugyanazon hálózaton)
- Gép elleni mód (AI)
- Valós idejű 1 másodperces polling
- Szoba chat (AI mód kivételével)
- Körönkénti visszaszámlálás (opcionális)
- Automatikus passz ha nincs lépés
- Automata játék vége detekció

### Testreszabás (szoba létrehozásakor)
- **Szoba neve** — megjelenítési név a lobbyban
- **Egyéni azonosító** — saját URL azonosító (betű/szám)
- **Időkorlát** — Ki / 30mp / 60mp / 120mp / Egyéni (5–600mp)
- **Korongszínek** — 12 szín előre meghatározva, mindkét játékoshoz külön, duplikáció tiltva
- **Nézők tiltása** — nézői csatlakozás letiltható
- **AI nehézség** — Könnyű / Közepes / Nehéz / Mester (csak AI módban)

### AI ellenfél
| Szint | Algoritmus | Mélység | Végjáték |
|-------|-----------|---------|----------|
| Könnyű | 60% véletlenszerű + greedy | 1 | Nincs |
| Közepes | Negamax | 2 | Nincs |
| Nehéz | Negamax + alpha-beta | 4 | ≤14 üres |
| Mester | Negamax + alpha-beta | 6 | ≤18 üres |

Az AI értékelő függvény: pozicionális súlymátrix (sarkok=120, X-négyzetek=−20) + mobilitás bónusz.

### Biztonság
- CSRF token minden POST kérésnél
- Útvonal bejárás elleni védelem az ID-n
- Bemeneti validáció (ID, lépés, név)
- Rate limit: 30 kérés / 10 másodperc munkamenetenként
- Fájlzárolás (flock) konkurens írások ellen
- Nézők nem tudnak lépni vagy chatozni

---

## Rendszerkövetelmények

### PHP
- **Verzió: PHP 8.0+** (ajánlott: 8.2 vagy 8.3)
- Kötelező kiterjesztések:
  - `mbstring` — UTF-8 szövegkezelés
  - `openssl` — CSRF token generálás (`random_bytes`)
  - `session` — játékos azonosítás (általában beépített)
  - `json` — játékállapot tárolás (általában beépített)
- Opcionális: `pcre` (beépített a legtöbb PHP-ban)

### Szerver
- Bármilyen PHP-t futtató webszerver:
  - Apache (mod_php vagy PHP-FPM)
  - Nginx + PHP-FPM
  - PHP beépített szerver (fejlesztéshez)
- **Nincs szükség**: adatbázisra, Node.js-re, Composer-re, npm-re
- **Fájlrendszer**: írási jog a `games/` mappára

### Böngésző
- Modern böngésző (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)
- JavaScript engedélyezve
- Nincs szükség pluginre vagy kiegészítőre

---

## Telepítés

### 1. Fájlok másolása

```bash
git clone https://nexnet.hu:5678/peter/reversi.git
cd reversi
```

Vagy töltsd le ZIP-ként és csomagold ki a webszerver gyökerébe.

### 2. `games/` mappa létrehozása

```bash
mkdir games
chmod 777 games    # Linux/Mac: írási jog szükséges
```

Windows esetén a mappa létrehozása elég.

### 3. PHP konfiguráció

Ellenőrizd, hogy az alábbi kiterjesztések engedélyezve vannak a `php.ini`-ben:

```ini
extension=mbstring
extension=openssl
```

### 4a. PHP beépített szerver (fejlesztés/tesztelés)

**Windows (start.bat segítségével):**
```
start.bat
```
A böngésző automatikusan megnyílik: `http://localhost:8000`

**Manuálisan:**
```bash
php -S localhost:8000 -t .
```

**Hordozható PHP (mellékelt, Windows):**
```bash
php\php.exe -c php\php.ini -S localhost:8000 -t .
```

### 4b. Apache konfiguráció

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/reversi
    ServerName reversi.pelda.hu

    <Directory /var/www/reversi>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`.htaccess` szükséges a gyökérben (opcionális, URL átíráshoz):
```apache
Options -Indexes
```

### 4c. Nginx konfiguráció

```nginx
server {
    listen 80;
    server_name reversi.pelda.hu;
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

## Fájlstruktúra

```
reversi/
├── index.php          # Lobby (szoba lista, létrehozás, csatlakozás)
├── game.php           # Játékszoba HTML váza
├── api.php            # Polling végpont (lépés, chat, törlés, csatlakozás)
├── lib.php            # Shared segédfüggvények (validáció, CSRF, rate-limit, játéklogika)
├── lib_ai.php         # AI logika (negamax + alpha-beta)
├── reversi.js         # Kliens: polling, render, board diff, chat, timer
├── reversi.css        # Játéknézet stílusok (sötét téma)
├── lobby.css          # Lobby stílusok (sötét téma)
├── start.bat          # Windows: egyclick szerver indítás
├── start_server.ps1   # PowerShell: szerver indítás
├── MIGRATIONS.md      # Játékállapot séma változásnapló
├── games/             # Játék JSON fájlok (gitignore-olva)
│   └── {id}.json      # Egy fájl játékonként
├── js/
│   ├── bootstrap.min.css   # Bootstrap 5 (vendored)
│   └── bootstrap.min.js
└── php/               # Hordozható PHP (Windows, gitignore-olva)
```

---

## Játékállapot séma

Minden aktív játék egy JSON fájlban tárolódik (`games/{id}.json`):

```json
{
  "creator": "Peter",
  "players": ["Peter", "Bob"],
  "turn": 0,
  "board": [[0,0,...], ...],
  "finished": false,
  "chat": [{"who": "Peter", "text": "Szia!", "ts": 1700000000}],
  "spectators": ["Nező1"],
  "timer": 60,
  "turnStartedAt": 1700000000,
  "ai": false,
  "ai_difficulty": "hard",
  "piece_colors": ["#111111", "#eeeeee"],
  "allow_spectators": true,
  "room_name": "Barátok szobája"
}
```

- `turn`: -1 (várakozás), 0 (fekete lép), 1 (fehér lép)
- `board`: 8×8 mátrix, 0=üres, 1=fekete, 2=fehér
- `timer`: 0=nincs időkorlát, egyébként másodperc
- `ai_difficulty`: `"easy"` | `"medium"` | `"hard"` | `"expert"`

---

## Automatikus takarítás

A `games/` mappa önmagát kezeli — nincs szükség cron jobra:

| Állapot | Törlés ideje |
|---------|-------------|
| Befejezett játék | 5 perc után |
| Várakozó (1 játékos) | 30 perc után |
| Elhagyott (2 játékos, inaktív) | 2 óra után |

A takarítás minden lobby oldalbetöltéskor fut.

---

## Játékszabályok

Standard Othello/Reversi:
- A fekete (1. játékos) kezd.
- Lépés érvényes, ha legalább egy ellenfél korongot befog.
- Ha egy játékosnak nincs érvényes lépése, automatikusan passzol.
- Ha mindkét játékosnak nincs érvényes lépése, a játék véget ér.
- A nyertes akinek több korongja van a táblán.

---

## Fejlesztési megjegyzések

- Nincs framework, nincs build lépés — tiszta PHP + vanilla JS
- A `flock()` zárolja a JSON fájlokat konkurens írások ellen
- Az AI lépés lustán értékelődik: minden lekérdezéskor fut ha az AI köre van (2mp minimális gondolkodási idő)
- A kliens 1 másodpercenként kérdezi le az állapotot; a játék végén leáll
- Minden UI szöveg magyar
- A CSRF token munkamenetenként generálódik és minden POST kérésnél validálva van

---

## Ismert korlátok (Alpha)

- Névütközés: két különböző felhasználó használhatja ugyanazt a nevet
- JSON fájl tárolás: magas terhelésnél (~50+ egyidejű játék) lassulhat
- WebSocket/SSE nincs: 1mp-es polling latencia
- Nincs ELO / statisztika rendszer
- Nincs fiók rendszer / bejelentkezés

---

## Tervezett fejlesztések

- SQLite/MySQL migráció
- WebSocket valós idejű kommunikáció
- Felhasználói fiókok és ELO rangsor
- Visszajátszás / lépésnapló
- Mobilbarát megjelenítés
- Hangeffektek

---

## Licenc

Személyes / oktatási célú projekt. Kereskedelmi felhasználás előtt egyeztesd a szerzővel.
