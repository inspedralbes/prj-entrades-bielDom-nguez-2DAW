## Context

El projecte actual té integració amb Google Gemini per a recomanacions personalitzades al feed "Triats per a tu". Aquesta integració inclou:
- Servei `GeminiHomeRecommendService` al backend
- Configuració a `config/services.php`
- Toggle al perfil d'usuari per activar/desactivar personalització
- Columna a la base de dades `user_settings.gemini_personalization_enabled`

L'usuari ha decidit eliminar aquesta funcionalitat completament.

## Goals / Non-Goals

**Goals:**
- Eliminar tota la integració amb Google Gemini
- Mantenir el funcionament de la resta de l'aplicació
- Eliminar dependència de l'API de Gemini

**Non-Goals:**
- No modificar altres funcionalitats de recomanació
- No eliminar altres serveis de Google (Maps, Ticketmaster)

## Decisions

**D1: Eliminar servei completament vs fer fallback**

Decision: Eliminar completament el servei `GeminiHomeRecommendService` en lloc de fer-lo servir com a fallback.

Rationale: L'usuari ha expressat que no vol Gemini, així que no té sentit mantenir codi inactiu.

**D2: Feed "Triats per a tu" quan Gemini no està disponible**

Decision: El feed "Triats per a tu" passarà a mostrar events ordenats per proximitat o cronològicament (mateix que "Destacats").

Rationale: Simplicitat en lloc d'implementar un nou sistema de recomanacions.

**D3: Eliminar columna de base de dades**

Decision: Eliminar la columna `gemini_personalization_enabled` de `user_settings`.

Rationale: columna innecessària si no s'utilitza Gemini.

## Risks / Trade-offs

**R1: Ús existent d'usuaris** → Mitigació: Cap usuari afectat ja que és opt-in i ara s'elimina completament

**R2: Documentació obsoleta** → Mitigació: Actualitzar especificacions que mencionen Gemini

## Migration Plan

1. Eliminar fitxers del backend (GeminiHomeRecommendService, configs)
2. Eliminar toggle del frontend (profile)
3. Eliminar columna de base de dades
4. Actualitzar FeedController per no cridar Gemini
5. Actualitzar documentació

## Open Questions

- Cal mantenir les credencials API de Gemini al .env o eliminar-les? → Eliminar-les