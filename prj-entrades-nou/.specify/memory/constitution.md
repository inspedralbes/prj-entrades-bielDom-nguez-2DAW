<!--
Sync Impact Report
- Version change: (unversioned placeholder) → 1.0.0
- Modified principles: N/A (initial adoption; template placeholders replaced)
- Added sections: Stack tecnològic i versions; Flux de treball, proves i compliment
- Removed sections: N/A
- Templates: .specify/templates/plan-template.md ✅ | .specify/templates/tasks-template.md ✅ | .specify/templates/spec-template.md — sense canvis obligatoris
- Follow-up TODOs: cap
-->

# Constitució del projecte Entrades (Ticketing)

## Core Principles

### I. Servidor com a única font de veritat

El backend (API Laravel) és l’única autoritat per a l’estat de negoci: disponibilitat d’entrades, reserves, pagaments i regles aplicables. El client (Nuxt), el servei de temps real (Socket.IO) i la memòria cau (Redis) no substitueixen aquesta autoritat. Qualsevol canvi durador o decisió que afecti la integritat del domini ha de persistir-se mitjançant l’API i la base de dades (PostgreSQL/PostGIS), amb polítiques de coherència definides al backend.

### II. Stack i versions acotades

Les versions següents són normatives per a aquest repositori: **Laravel 11** amb **PHP 8.3**; **Nuxt 3** amb **Vue 3.4+** i **Pinia** per a l’estat del client; **Node.js 20 (LTS)** amb **Socket.IO 4.7** per al canal temps real; **PostgreSQL 16** amb **PostGIS 3.4**; **Redis 7.2** per a memòria cau, cues o bloquejos segons el disseny; **Gemini 1.5 Flash** via **Google AI SDK** per a capacitats d’IA. No s’introdueixen runtimes, frameworks de substitució o salts de versió majors sense esmena de la constitució i pla de migració on calgui.

### III. Temps real subordinat a l’API

Els esdeveniments emesos pel servei Socket.IO han de reflectir estat ja validat i persistit pel backend, o fluxos explícitament coordinats amb l’API. El client no ha de basar decisions crítiques (pagaments, assignació final d’entrades, etc.) només en missatges en temps real sense confirmació contra l’API o dades autoritàries del servidor.

### IV. Integritat de dades i geoespai

Les dades de domini i les consultes geoespacials es modelen i s’implementen principalment al backend sobre **PostgreSQL/PostGIS**. Les migracions, restriccions i transaccions que garanteixin la integritat són responsabilitat del backend; Redis s’usa d’acord amb el disseny (per exemple cau o bloquejos) sense contradir l’estat autoritatiu de la base de dades.

### V. IA assistencial (Gemini 1.5 Flash)

L’ús de **Gemini 1.5 Flash** és **assistencial**: no substitueix les regles de negoci ni actua com a font de veritat. Les sortides del model s’han de validar abans d’afectar l’estat del sistema. Els secrets, credencials i dades personals no s’envien al model sense política explícita de privacitat i minimització de dades.

## Stack tecnològic i versions

| Capa | Tecnologia | Versió |
|------|------------|--------|
| Backend API | Laravel | 11 |
| Runtime backend | PHP | 8.3 |
| Frontend | Nuxt 3 (Vue 3.4+), Pinia | 3 / 3.4+ |
| Temps real | Node.js, Socket.IO | 20 LTS, 4.7 |
| Base de dades | PostgreSQL, PostGIS | 16, 3.4 |
| Cau / bloquejos | Redis | 7.2 |
| IA | Gemini (Google AI SDK) | 1.5 Flash |

## Flux de treball, proves i compliment

Les especificacions i plans de funcionalitat han de ser compatibles amb els principis anteriors. Els contractes d’API (p. ex. OpenAPI o fitxers a `contracts/`) defineixen el comportament esperat del servidor; el frontend i els clients temps real s’alineen amb aquests contractes. Abans de fusionar canvis que afectin domini o versions del stack, la revisió ha de verificar el compliment d’aquesta constitució; les excepcions s’han de documentar (per exemple a la secció *Complexity Tracking* del pla) i aprovar-se explícitament.

## Governance

Aquest document té prioritat sobre altres guies de desenvolupament quan hi hagi conflicte sobre principis de governança del projecte. Les esmenes requereixen canvi de versió segons semver semàntic: **MAJOR** per canvis incompatibles o eliminació/redefinició de principis; **MINOR** per afegir principis o seccions amb orientació nova; **PATCH** per aclariments o correccions sense canvi de significat. La data **Last Amended** s’actualitza en cada esmena; **Ratified** conserva la data d’adopció inicial. Les revisions periòdiques han de comprovar que el codi i els desplegaments respecten el stack i la font de veritat al servidor.

**Version**: 1.0.0 | **Ratified**: 2026-04-11 | **Last Amended**: 2026-04-11
