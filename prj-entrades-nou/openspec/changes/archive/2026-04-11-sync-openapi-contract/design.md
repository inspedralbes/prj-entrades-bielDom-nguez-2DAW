## Context

El contracte OpenAPI actual a `specs/001-seat-map-entry-validation/contracts/openapi.yaml` conté endpoints definits manualment però no Reflecteix l'estat actual de la API Laravel. Cal analitzar `backend-api/routes/api.php` per identificar totes les rutes reals i comparar-les amb el contracte existent.

### Rutes a analitzar
- Endpoints existents al contracte actual
- Endpoints de fase 7: feed, search/events, saved-events, user/profile, user/settings
- Codis de resposta HTTP i esquemes de dades

### Restriccions
- Mantenir compatibilitat amb clients existents
- Validar YAML OpenAPI 3 segons especificació
- Assegurar que tots els endpoints tenen exemples vàlids

## Goals / Non-Goals

**Goals:**
- Mapear totes les rutes API de `backend-api/routes/api.php`
- Identificar endpoints que falten al contracte
- Actualitzar Esquemes per reflectir models de dades reals
- Afegir codis d'error 4xx adequats

**Non-Goals:**
- No modificar la lògica del backend
- No crear nous endpoints (només documentar els existents)
- No implementar autenticació OAuth (si no existeix)

## Decisions

1. **Eina de validació**: Utilitzar `swagger-cli` o similar per validar el YAML OpenAPI 3
2. **Estandard de codis d'error**: seguiment de RFC 7807 (Problem Details for HTTP APIs)
3. **Gestió de versions**: Incrementar versió del document si canvis significatius (> major si breaking changes)

## Risks / Trade-offs

- [Risk] Rutes definides dinàmicament → Revisar manualment totes les rutes al codi
- [Risk] Diferències entre entorns → Documentar assumptions
- [Trade-off] Completesa vs. temps → Prioritzar endpoints amb més ús