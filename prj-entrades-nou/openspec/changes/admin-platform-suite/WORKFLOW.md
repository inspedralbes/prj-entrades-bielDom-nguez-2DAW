# Flux d’entrega — `admin-platform-suite`

Aquest document fixa el **procés obligatori** per implementar la suite d’administració: cada **bloc entregable** (alineat amb les pàgines / àrees A–E i dependències del `tasks.md`) es desenvolupa en una **branca dedicada** des de `dev`, es valida **al complet** (frontend + backend), es fusiona a `dev` i només llavors es donen per **tancades** les tasques corresponents.

## 1. Principis (no negociables)

1. **Branca des de `dev`**: cada entrega comença amb `dev` actualitzat i una branca nova (`feature/admin-…`, vegeu §3).
2. **Codi complet del tros**: en la branca només hi ha el que cal per al **bloque entregable** definit al §4 (API, socket si escau, Nuxt, tests). No es fusiona “a mitges”.
3. **Verificació al 100% abans del merge**:
   - **Frontend**: prova **manual** al navegador (fluxos de la pàgina o funcionalitat; dades coherents, errors gestionats, rol `admin`).
   - **Backend**: **tests automàtics** que cobreixin la funcionalitat nova o modificada; la suite rellevant ha de passar **sense fallades** (`php artisan test`, o el subconjunt acordat, sempre incloent els nous tests).
4. **Socket / temps real** (si el tros ho inclou): comprovar al navegador que els esdeveniments en viu (o el comptador de presència) es comporten com al spec; si hi ha proves automàtiques al `socket-server`, han de passar també.
5. **Commit, push i merge a `dev`**: només després dels punts anteriors. Missatges de commit clars (català o convenció del repo).
6. **Actualitzar `tasks.md`**: passar a **`[x]`** totes les sub-tasques del bloque lliurat. **Prohibit** deixar caselles a mitges dins d’un bloque declarat tancat.
7. **Següent bloque**: es crea una **nova branca des de `dev`** (ja amb el merge anterior) i es repeteix el cicle.

## 2. Què no està permès

- Fusionar a `dev` amb funcionalitat **parcial** o amb tests que fallen.
- Donar per feta una tasca al `tasks.md` amb codi incomplet o sense proves adequades.
- Obrir una PR “gran” que barreja diversos blocs A–E sense haver-los validat per separat (excepte si l’equip acorda explícitament un altre pla; el flux per defecte és **un bloc = una branca = un merge**).

## 3. Nomenclatura de branques (recomanat)

Prefix: `feature/admin-platform-` + identificador curt del bloc, p. ex.:

| Bloc (referència `tasks.md`) | Exemple de branca |
|------------------------------|-------------------|
| §1 Contracte i model + OpenAPI inicial | `feature/admin-platform-contract-openapi` |
| §2–3 Dashboard global + presència (pàgina A) | `feature/admin-platform-dashboard-a` |
| §4 CRUD esdeveniments + Discovery (pàgina B) | `feature/admin-platform-events-b` |
| §5–6 Monitor esdeveniment + socket detall (C) | `feature/admin-platform-monitor-c` |
| §7 Usuaris i tiquets (pàgina D) | `feature/admin-platform-users-d` |
| §8 Informes (pàgina E) | `feature/admin-platform-reports-e` |
| §9–10 Stores + pàgines (si es fa en el mateix tros que el backend corresponent, fusionar nomenclatura amb el bloc funcional) | alineat amb A–E |

Ajustar els noms si un bloc es parteix en PRs més petites, però **mantenir una branca = un entregable coherent**.

## 4. Mapatge pàgines admin ↔ seccions del `tasks.md`

| Pàgina / àrea | Seccions principals `tasks.md` |
|---------------|--------------------------------|
| **A** Dashboard global | §2, §3 (i part de §6 si cal socket nou), §9–10.1 |
| **B** Esdeveniments (CRUD + Discovery) | §4, parts de §1 OpenAPI, §10.2 |
| **C** Monitor temps real esdeveniment | §5, §6, §10.3 |
| **D** Usuaris i tiquets | §7, §10.4 |
| **E** Informes | §8, §10.5 |

La **§1** (contracte/dades) sol precedir o anar **dins** del primer bloc que necessiti els nous paths; la **§11–12** s’apliquen al **tancament** de cada PR (lint, proves, actualització caselles) i la **§12** final quan tot el change estigui complet.

## 5. Checklist abans de merge a `dev`

- [ ] `dev` actualitzat abans de crear la branca.
- [ ] Totes les sub-tasques del bloc implementades.
- [ ] Proves manual al navegador (admin) pel flux del bloc.
- [ ] Tests backend verds (i socket si escau).
- [ ] `tasks.md`: caselles del bloc marcades `[x]`.
- [ ] Commit + push; merge (o PR revisada segons política de l’equip).

## 6. Rol de l’agent / Cursor

Durant el desenvolupament d’un bloc, l’agent ha de:

1. Implementar el codi al repositori (branca activa).
2. **Executar** els tests backend (i altres comandes de verificació del repo).
3. Quan sigui possible, **verificar el frontend** amb les eines de navegador disponibles (fluxos principals de la pàgina admin del bloc).
4. No declarar el bloc “fet” fins que la verificació del §1–3 d’aquest document estigui satisfeta.

Si algun entorn local (Docker, credencials) impedeix una prova, s’ha de **documentar al PR** el que s’ha pogut executar i el que queda per validar manualment per l’humà — sense marcar les tasques com `[x]` fins que l’equip confirmi el criteri al 100%.
