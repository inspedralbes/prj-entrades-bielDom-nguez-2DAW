-- Sincronització idempotent de l’usuari admin de desenvolupament.
-- Email: admin@example.com · contrasenya: Admin1234 (mínim 8 caràcters).
-- S’executa des de docker/dev/entrypoint-api.sh en cada arrencada (APP_ENV=local) per:
--   - alinear el hash amb inserts.sql si el volum de Postgres era antic, o
--   - crear/actualitzar la fila sense caler recrear el volum.
-- Cal mantenir el hash alineat amb database/inserts.sql (mateix bloc UPSERT).

INSERT INTO users (name, username, email, password, created_at, updated_at)
VALUES (
    NULL,
    'admin',
    'admin@example.com',
    '$2y$10$0k9c3OaQlZKKFJKSur41XOi1Bp3sWr72wwR1uCNcm5qn22IJsCuNG',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
)
ON CONFLICT (email) DO UPDATE SET
    password = EXCLUDED.password,
    name = EXCLUDED.name,
    username = EXCLUDED.username,
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'App\Models\User', u.id
FROM roles r
INNER JOIN users u ON u.email = 'admin@example.com'
WHERE r.name = 'admin' AND r.guard_name = 'web'
ON CONFLICT (role_id, model_id, model_type) DO NOTHING;
