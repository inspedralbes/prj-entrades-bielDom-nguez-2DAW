# Proves de càrrega (T053)

Aquesta carpeta conté **proves complementàries** al `socket-server` i a l’API. No substitueixen Cypress ni PHPUnit.

## k6 — HTTP al `socket-server` (health)

Requisit: [k6](https://k6.io/) instal·lat al PATH.

Des del monorepo (amb el socket escoltant a `http://localhost:3001`):

```bash
k6 run prj-entrades-nou/load/k6-socket-health.js
```

Objectiu orientatiu: el servidor respon `200` al `GET /health` sota concurrència; útil com a smoke de rendiment HTTP del procés Node.

## Socket.IO “real”

k6 no inclou client Socket.IO oficial. Per stress de **connexions WebSocket** massives:

- provar [xk6-socketio](https://github.com/MStoykov/xk6-socketio) o scripts Node amb `socket.io-client` (moltes connexions amb `eventId` a la query), o
- Artillery amb [engine socketio](https://www.artillery.io/docs/guides/guides/socketio-reference) (configuració pròpia del projecte).

Els criteris **SC-002** / **SC-004** del [spec.md](../specs/001-seat-map-entry-validation/spec.md) s’han de validar amb l’eina triada i un entorn estable (mateix `JWT_SECRET` entre API i socket per al namespace `/private`).
