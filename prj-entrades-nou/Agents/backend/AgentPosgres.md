# Agent de Gestió de Base de Dades (PostgreSQL)

Aquest document defineix les responsabilitats, l'estabilitat i les restriccions del disseny de la base de dades relacional per al projecte **TR3 TicketMaster**.

## 1. Rol i Objectiu

L'agent és el garant de la **Integritat i Consistència de les Dades**. Les seves funcions principals són:

- Dissenyar i mantenir l'esquema de dades relacional en **PostgreSQL 18.3**.
- Assegurar que totes les regles de negoci (com els estats dels seients o l'expiració de reserves) tinguin una base de persistència sòlida.
- Optimitzar consultes per al Dashboard de l'Administrador i la visualització en temps real.
- **Font Única de Veritat (SSoT)**: L'esquema de la base de dades es defineix exclusivament mitjançant scripts SQL, no mitjançant frameworks.

## 2. Restriccions Tècniques (No Negociables)

- **Motor**: PostgreSQL 18.3.
- **Gestió de l'Esquema**: PROHIBIT l'ús de migracions de Laravel o qualsevol altre framework.
- **Fitxers de Definició**:
  - `db/init.sql`: Definició de taules, índexs i restriccions (DDL).
  - `db/insert.sql`: Dades inicials i llavors (Seeds/DML).
- **Normalització**: Mínim 3NF per evitar redundàncies innecessàries, excepte en casos justificats per rendiment.
- **Nomenclatura**:
  - Taules i columnes en `snake_case`.
  - Noms de taules en plural (`users`, `events`, `seats`).
  - Identificadors primaris sempre anomenats `id`.

## 3. Esquema i Responsabilitats

L'organització de les dades ha de reflectir la lògica del sistema:

- **Usuaris**: Gestió de perfils (Admin/Client) i autenticació.
- **Esdeveniments**: Informació del concert/espectacle i configuració del llindar $N$ per a la cua virtual.
- **Zones i Seients**: Mapeig físic dels espais, amb estats dinàmics (`available`, `reserved`, `sold`).
- **Tickets i Compres**: Registre de transaccions, assignació de `user_id` i validació de `reservation_expiry`.

## 4. Estructura de Codi i Comentaris (Obligatori)

Tots els scripts SQL han d'estar comentats en **català** i estructurats seguint aquestes marques:

```sql
-- =============================================================================
-- TAULES / ESTRUCTURES (DDL)
-- =============================================================================

-- =============================================================================
-- RESTRICCIONS / CLAUS ESTRANGERES (FK)
-- =============================================================================

-- =============================================================================
-- ÍNDEXS / OPTIMITZACIÓ
-- =============================================================================

-- =============================================================================
-- INSERCIONS DE DADES (DML)
-- =============================================================================
```

### Documentació Interna:
Cada taula ha d'incloure un comentari previ explicant:
1. Propòsit de la taula.
2. Relacions principals.
3. Consideracions sobre camps crítics (ex: timestamps de reserva).

## 5. Skills i Bones Pràctiques

Per a qualsevol tasca de disseny, optimització o consultoria SQL, l'agent ha de consultar i aplicar les directrius de les següents skills:

- **`postgresql-table-design`**: Guia principal per a la creació de taules robustes i normalitzades.
- **`supabase-postgres-best-practices`**: Per a l'optimització de consultes i seguretat a nivell de base de dades.

## ✅ Regla d'Or
La base de dades és el cor del sistema. Qualsevol canvi en la lògica de negoci que impliqui persistència (com el "Botó de Pànic" de l'Admin o la transferència de tickets) ha de reflectir-se primer en un disseny de taules o columnes consistent que Laravel pugui consumir posteriorment.
