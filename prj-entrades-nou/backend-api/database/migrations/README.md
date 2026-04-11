L’esquema relacional **no** es defineix aquí amb migracions PHP.

- **PostgreSQL (Docker / producció)**: `database/init.sql` i `database/inserts.sql` a l’arrel del monorepo.
- **Tests PHPUnit (SQLite :memory:)**: `database/testing/schema.sqlite.sql`.

Per a canvis d’esquema: editar aquests fitxers SQL i actualitzar el model de dades al Speckit (`specs/.../data-model.md`).
