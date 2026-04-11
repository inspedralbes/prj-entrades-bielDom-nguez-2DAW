# socket-server

Servei **Socket.IO** + (futur) pub/sub Redis i generació QR.

## JWT compartit amb `backend-api`

El handshake amb JWT d’API i la validació de tokens de ticket han d’usar el **mateix secret** que Laravel (`JWT_SECRET`, o si no existeix **`APP_KEY`**, igual que `config/jwt.php` al backend). Algorisme **HS256**. El payload emès per l’API inclou `sub` (id d’usuari) i `roles` (array de strings).

- **Públic**: connexió al servidor principal amb `?eventId=` al query del handshake → entra al room `event:{eventId}`.
- **Privat**: namespace Socket.IO **`/private`**, token a `handshake.auth.token` (opcionalment amb prefix `Bearer `). Es fa `join` automàtic a `user:{sub}` del JWT.

Esdeveniments orientatius: `seat:contention`, `countdown:resync`, `ticket:validated`, `admin:metrics`.

No versionar secrets; només `.env.example` amb placeholders.
