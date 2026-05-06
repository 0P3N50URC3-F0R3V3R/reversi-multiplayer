# Schema Migrations Log

## Cycle 1 — 2026-05-06

Additive changes to `games/{id}.json`. All keys have safe defaults; old game files keep working.

| Key | Type | Default | Added in | Notes |
|-----|------|---------|----------|-------|
| `chat` | array of `{who, text, ts}` | `[]` | Phase C | Players only (spectators read) |
| `spectators` | array of strings | `[]` | Phase C | Names of observers |
| `timer` | int (seconds, 0=off) | `0` | Phase B | Set at game creation |
| `turnStartedAt` | int (unix ts) or null | `null` | Phase C | Reset on each move and auto-pass |
| `ai` | bool | `false` | Phase C | Solo AI game flag |

### Backward compatibility

`api.php` fills any missing keys on load via `$game += [...]` before any logic runs. Existing game files (e.g., `games/69fb2950d8885.json`) continue to work with all new features defaulted to safe values.

### AI player name

AI games use the reserved player name `"Gép"`. Human players should not register with this name (not enforced in Cycle 1; name collision = human is treated as the AI slot).
