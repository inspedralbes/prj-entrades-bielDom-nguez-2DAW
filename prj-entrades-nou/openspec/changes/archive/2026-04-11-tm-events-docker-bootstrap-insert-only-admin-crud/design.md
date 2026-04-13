## Context

TicketmasterEventImportService existeix. Cal:
1. Executar en Docker bootstrap (entrypoint script)
2. Job daily insert-only
3. Admin CRUD complet

## Goals / Non-Goals

**Goals:**
- Import inicial TM en docker compose up (primer cop)
- Sync diari: INSERT només nous external_tm_id
- Admin CRUD: POST/GET/PATCH/DELETE /api/admin/events

**Non-Goals:**
- No modificar UI admin (tasca separada)
- No sobreescriure events existents