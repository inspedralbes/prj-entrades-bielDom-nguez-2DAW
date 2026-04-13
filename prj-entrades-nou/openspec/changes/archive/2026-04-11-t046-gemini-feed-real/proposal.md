## Why

El deute P-T046-GEMINI-REAL de `docs/tasksPendents.md` indica que `GeminiHomeRecommendService` és stub i cal implementar crida real a Gemini API per al feed "Triats per a tu".

## What Changes

- Implementar servei Gemini real (no stub)
- Afegir `GEMINI_API_KEY` a backend
- Respectar `user_settings.gemini_personalization_enabled`
- Fallback si API falla
- Retornar només IDs d'esdeveniments existents (no compra/reserva)

## Capabilities

### New Capabilities
- `gemini-feed-recommendations`: Motor Gemini real per a feed

### Modified Capabilities
- (none)

## Impact

- `backend-api/app/Services/Recommend/GeminiHomeRecommendService.php`
- `.env.example` - GEMINI_API_KEY