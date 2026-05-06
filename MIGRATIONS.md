# Schema Migrations Log

## Cycle 1 — 2026-05-06

Additive changes to `games/{id}.json`. All keys have safe defaults; old game files keep working.

| Key | Type | Default | Added in |
|-----|------|---------|----------|
| `chat` | array | `[]` | Phase C |
| `spectators` | array | `[]` | Phase C |
| `timer` | int (seconds, 0=off) | `0` | Phase B |
| `turnStartedAt` | int (unix ts) or null | `null` | Phase C |
| `ai` | bool | `false` | Phase C |
