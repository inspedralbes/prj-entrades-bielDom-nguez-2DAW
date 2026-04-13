## Why

El projecte ja no vol utilitzar Google Gemini per a recomanacions. Volem eliminar tota la integració amb Gemini del codi.

## What Changes

- Eliminar `GeminiHomeRecommendService` del backend
- Eliminar configuració `gemini` de `config/services.php`
- Eliminar variables d'entorn `GEMINI_MODEL`, `GEMINI_API_KEY` del `.env.example`
- Eliminar camp `gemini_personalization_enabled` de la taula `user_settings`
- Eliminar el toggle de Gemini a la pàgina de perfil
- Eliminar crides a Gemini des del `FeedController`
- Eliminar referències a Gemini a la documentació i especificacions

## Capabilities

### New Capabilities
- (none)

### Modified Capabilities
- (none)

## Impact

- **Backend**: Eliminar servei, controller, config de Gemini
- **Frontend**: Eliminar toggle de personalització Gemini al perfil
- **Base de dades**: Eliminar columna `gemini_personalization_enabled` de `user_settings`
- **Documentació**: Actualizar especificacions que mencionen Gemini