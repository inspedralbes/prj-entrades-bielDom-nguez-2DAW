## 1. Backend - Eliminació de codi Gemini

- [x] 1.1 Eliminar fitxer `app/Services/Recommend/GeminiHomeRecommendService.php`
- [x] 1.2 Eliminar configuració `gemini` de `config/services.php`
- [x] 1.3 Eliminar referències a Gemini de `FeedController.php`
- [x] 1.4 Eliminar variables GEMINI_MODEL, GEMINI_API_KEY de `.env.example`

## 2. Base de dades

- [x] 2.1 Eliminar columna `gemini_personalization_enabled` de `user_settings` a `database/init.sql`
- [x] 2.2 Eliminar columna de `database/testing/schema.sqlite.sql`

## 3. Frontend

- [x] 3.1 Eliminar toggle de Gemini a `pages/profile/index.vue`
- [x] 3.2 Eliminar referències a `gemini_personalization_enabled` a `UserProfileController.php`

## 4. Documentació

- [x] 4.1 Actualitzar `specs/001-seat-map-entry-validation/spec.md` per eliminar referències a Gemini
- [x] 4.2 Actualitzar altres documents que mencionen Gemini (si cal)