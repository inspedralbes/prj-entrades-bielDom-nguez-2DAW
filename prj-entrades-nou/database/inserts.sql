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
    'Test User',
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
