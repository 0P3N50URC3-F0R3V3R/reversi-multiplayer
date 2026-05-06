# Reversi Többjátékos

Kétjátékos Reversi/Othello böngészős játék. PHP backend, vanilla JS frontend, JSON fájl alapú tárolás.

## Futtatás

```
php -S localhost:8000
```

Majd nyisd meg: http://localhost:8000

## Fájlstruktúra

- `index.php` — lobby (játék létrehozása / csatlakozás)
- `game.php` — játékszoba
- `api.php` — polling végpont (lépés, chat, törlés)
- `lib.php` — validáció, CSRF, rate-limit segédfüggvények
- `lib_ai.php` — AI ellenfél logika
- `reversi.js` — kliens oldali renderelés és polling
- `reversi.css` — stílusok
- `games/` — játékállapot JSON fájlok (gitignored)

## Sémaváltozások

Lásd `MIGRATIONS.md`.
