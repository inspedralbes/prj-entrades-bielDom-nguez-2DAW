## 1. Test setup

- [x] 1.1 Revisar socket-server/src/index.js handlers
- [x] 1.2 Comprovar socket.io-client disponible a frontend-nuxt

## 2. Public channel tests

- [x] 2.1 Crear test connexio publica sense JWT rep server:hello
- [x] 2.2 Crear test subscripcio a room event:{id}
- [x] 2.3 Crear test public broadcast rep seat:contention

## 3. Private namespace tests

- [x] 3.1 Crear test /private rebutja sense token
- [x] 3.2 Crear test /private accepta JWT valid
- [x] 3.3 Crear test /private uneix a room user:{id}

## 4. Run and verify

- [x] 4.1 Executar tests localment (socket-server healthy)
- [x] 4.2 Verificar tests passen
- [x] 4.3 Actualitzar docs/tasksPendents.md marcar P-T013 com a cobert