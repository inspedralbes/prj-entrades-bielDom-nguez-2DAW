-- Esquema SQLite per a PHPUnit (phpunit.xml: DB_CONNECTION=sqlite :memory:).
-- Sense PostGIS: venues.location és TEXT (nullable). Paritat funcional amb database/init.sql.

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    created_at VARCHAR(255),
    updated_at VARCHAR(255)
);

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at VARCHAR(255)
);

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY NOT NULL,
    user_id INTEGER,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT NOT NULL,
    last_activity INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX sessions_user_id_index ON sessions (user_id);
CREATE INDEX sessions_last_activity_index ON sessions (last_activity);

CREATE TABLE cache (
    key VARCHAR(255) PRIMARY KEY NOT NULL,
    value TEXT NOT NULL,
    expiration INTEGER NOT NULL
);

CREATE INDEX cache_expiration_index ON cache (expiration);

CREATE TABLE cache_locks (
    key VARCHAR(255) PRIMARY KEY NOT NULL,
    owner VARCHAR(255) NOT NULL,
    expiration INTEGER NOT NULL
);

CREATE INDEX cache_locks_expiration_index ON cache_locks (expiration);

CREATE TABLE jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts INTEGER NOT NULL,
    reserved_at INTEGER,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE INDEX jobs_queue_index ON jobs (queue);

CREATE TABLE job_batches (
    id VARCHAR(255) PRIMARY KEY NOT NULL,
    name VARCHAR(255) NOT NULL,
    total_jobs INTEGER NOT NULL,
    pending_jobs INTEGER NOT NULL,
    failed_jobs INTEGER NOT NULL,
    failed_job_ids TEXT NOT NULL,
    options TEXT,
    cancelled_at INTEGER,
    created_at INTEGER NOT NULL,
    finished_at INTEGER
);

CREATE TABLE failed_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at VARCHAR(255) NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE venues (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    external_tm_id VARCHAR(255),
    location TEXT,
    created_at VARCHAR(255),
    updated_at VARCHAR(255)
);

CREATE UNIQUE INDEX venues_external_tm_id_unique ON venues (external_tm_id) WHERE external_tm_id IS NOT NULL;

CREATE TABLE events (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    external_tm_id VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    hold_ttl_seconds INTEGER NOT NULL DEFAULT 240,
    venue_id INTEGER NOT NULL,
    starts_at VARCHAR(255) NOT NULL,
    hidden_at VARCHAR(255),
    category VARCHAR(255),
    seat_layout TEXT,
    tm_sync_paused INTEGER NOT NULL DEFAULT 0,
    image_url VARCHAR(1024),
    price NUMERIC,
    tm_url VARCHAR(1024),
    tm_category VARCHAR(100),
    is_large_event INTEGER NOT NULL DEFAULT 0,
    description TEXT,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE CASCADE
);

CREATE INDEX events_external_tm_id_index ON events (external_tm_id);

CREATE TABLE zones (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    event_id INTEGER NOT NULL,
    external_zone_key VARCHAR(255),
    label VARCHAR(255) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
);

CREATE TABLE seats (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    event_id INTEGER NOT NULL,
    zone_id INTEGER NOT NULL,
    external_seat_key VARCHAR(255) NOT NULL,
    status VARCHAR(255) NOT NULL DEFAULT 'available',
    current_hold_id VARCHAR(255),
    held_until VARCHAR(255),
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    UNIQUE (event_id, external_seat_key),
    FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones (id) ON DELETE CASCADE
);

CREATE INDEX seats_status_index ON seats (status);
CREATE INDEX seats_event_id_status_index ON seats (event_id, status);

CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    user_id INTEGER NOT NULL,
    event_id INTEGER NOT NULL,
    hold_uuid VARCHAR(255),
    state VARCHAR(255) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
    total_amount NUMERIC,
    quantity INTEGER,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    UNIQUE (hold_uuid),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
);

CREATE INDEX orders_state_index ON orders (state);

CREATE TABLE order_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    order_id INTEGER NOT NULL,
    seat_id INTEGER,
    unit_price NUMERIC NOT NULL,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    UNIQUE (order_id, seat_id),
    FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES seats (id) ON DELETE CASCADE
);

CREATE TABLE tickets (
    id VARCHAR(255) PRIMARY KEY NOT NULL,
    public_uuid VARCHAR(255) NOT NULL UNIQUE,
    order_line_id INTEGER NOT NULL UNIQUE,
    status VARCHAR(255) NOT NULL,
    qr_payload_ref VARCHAR(255),
    jwt_expires_at VARCHAR(255),
    used_at VARCHAR(255),
    validator_id INTEGER,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    FOREIGN KEY (order_line_id) REFERENCES order_lines (id) ON DELETE CASCADE,
    FOREIGN KEY (validator_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE INDEX tickets_status_index ON tickets (status);

CREATE TABLE saved_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    user_id INTEGER NOT NULL,
    event_id INTEGER NOT NULL,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    UNIQUE (user_id, event_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
);

CREATE TABLE user_settings (
    user_id INTEGER PRIMARY KEY NOT NULL,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE friend_invites (
    id VARCHAR(255) PRIMARY KEY NOT NULL,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER,
    receiver_email VARCHAR(255),
    status VARCHAR(255) NOT NULL DEFAULT 'pending',
    invite_token VARCHAR(255),
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX friend_invites_invite_token_unique ON friend_invites (invite_token);
CREATE INDEX friend_invites_status_index ON friend_invites (status);

CREATE TABLE ticket_transfers (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    ticket_id VARCHAR(255) NOT NULL,
    from_user_id INTEGER NOT NULL,
    to_user_id INTEGER NOT NULL,
    status VARCHAR(255) NOT NULL DEFAULT 'completed',
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX ticket_transfers_status_index ON ticket_transfers (status);

CREATE TABLE social_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    user_id INTEGER NOT NULL,
    actor_user_id INTEGER,
    type VARCHAR(64) NOT NULL,
    payload TEXT NOT NULL DEFAULT '{}',
    read_at VARCHAR(255),
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE INDEX social_notifications_user_created_index ON social_notifications (user_id, created_at DESC);

CREATE TABLE tm_discovery_sync (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    cursor TEXT,
    last_run_at VARCHAR(255),
    last_error TEXT,
    created_at VARCHAR(255),
    updated_at VARCHAR(255)
);

CREATE TABLE permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    UNIQUE (name, guard_name)
);

CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    UNIQUE (name, guard_name)
);

CREATE TABLE model_has_permissions (
    permission_id INTEGER NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id INTEGER NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type),
    FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
);

CREATE INDEX model_has_permissions_model_id_model_type_index ON model_has_permissions (model_id, model_type);

CREATE TABLE model_has_roles (
    role_id INTEGER NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id INTEGER NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type),
    FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
);

CREATE INDEX model_has_roles_model_id_model_type_index ON model_has_roles (model_id, model_type);

CREATE TABLE role_has_permissions (
    permission_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    PRIMARY KEY (permission_id, role_id),
    FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
);

-- Auditoria panell admin (dashboard)
CREATE TABLE admin_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    admin_user_id INTEGER NOT NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(120) NOT NULL,
    entity_id INTEGER,
    summary TEXT NOT NULL,
    ip_address VARCHAR(45),
    created_at VARCHAR(255),
    updated_at VARCHAR(255),
    FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX admin_logs_created_at_index ON admin_logs (created_at);
CREATE INDEX admin_logs_admin_user_id_index ON admin_logs (admin_user_id);
