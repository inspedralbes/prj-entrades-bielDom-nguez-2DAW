-- Dades inicials de desenvolupament (després de l'esquema).
-- Rols Spatie (guard web; alineat amb database/seeders/RoleSeeder.php).

INSERT INTO roles (name, guard_name, created_at, updated_at) VALUES
    ('user', 'web', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('validator', 'web', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('admin', 'web', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (name, guard_name) DO NOTHING;

-- Usuari de prova (contrasenya: "password") — hash bcrypt estàndard Laravel
INSERT INTO users (name, username, email, password, created_at, updated_at)
VALUES (
    NULL,
    'testuser',
    'test@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
)
ON CONFLICT (email) DO NOTHING;

INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'App\Models\User', u.id
FROM roles r
INNER JOIN users u ON u.email = 'test@example.com'
WHERE r.name = 'user' AND r.guard_name = 'web'
ON CONFLICT (role_id, model_id, model_type) DO NOTHING;

-- Administrador de desenvolupament (contrasenya: "Admin1234", mínim 8 caràcters) — bcrypt
-- ON CONFLICT DO UPDATE: si el volum de Postgres ja existia amb una fila antiga, s’actualitza el hash.
-- Mateix contingut que database/docker-dev-ensure-admin.sql (s’executa també a cada arrencada de l’API local).
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

-- Esdeveniment de prova: mapa cinema (seat_layout buit = tots els seients lliures a JSONB)
INSERT INTO venues (name, city, created_at, updated_at)
SELECT 'Venue interactiu TR3', 'Barcelona', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM venues v WHERE v.name = 'Venue interactiu TR3');

INSERT INTO events (name, venue_id, starts_at, seat_layout, hold_ttl_seconds, price, created_at, updated_at)
SELECT
    'Concert de prova — mapa cinema',
    v.id,
    (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') + INTERVAL '45 days',
    '{}'::jsonb,
    240,
    25.00,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM venues v
WHERE v.name = 'Venue interactiu TR3'
  AND NOT EXISTS (SELECT 1 FROM events e WHERE e.name = 'Concert de prova — mapa cinema');
