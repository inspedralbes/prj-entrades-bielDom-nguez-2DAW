-- Esquema PostgreSQL + PostGIS — font de veritat per a desenvolupament Docker i documentació.
-- S'executa automàticament en crear el volum de dades (docker-entrypoint-initdb.d).
-- No usar migracions Laravel per a l'esquema (vegeu specs i database/README.md).

CREATE EXTENSION IF NOT EXISTS postgis;

-- ---------------------------------------------------------------------------
-- Laravel: usuaris, sessió, cua, cache
-- ---------------------------------------------------------------------------

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255),
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT REFERENCES users (id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT NOT NULL,
    last_activity INTEGER NOT NULL
);

CREATE INDEX sessions_user_id_index ON sessions (user_id);
CREATE INDEX sessions_last_activity_index ON sessions (last_activity);

CREATE TABLE cache (
    key VARCHAR(255) PRIMARY KEY,
    value TEXT NOT NULL,
    expiration BIGINT NOT NULL
);

CREATE INDEX cache_expiration_index ON cache (expiration);

CREATE TABLE cache_locks (
    key VARCHAR(255) PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration BIGINT NOT NULL
);

CREATE INDEX cache_locks_expiration_index ON cache_locks (expiration);

CREATE TABLE jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts SMALLINT NOT NULL,
    reserved_at INTEGER,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE INDEX jobs_queue_index ON jobs (queue);

CREATE TABLE job_batches (
    id VARCHAR(255) PRIMARY KEY,
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
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------------
-- Domini: venues, events, zones, seats
-- ---------------------------------------------------------------------------

CREATE TABLE venues (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(500),
    city VARCHAR(255),
    external_tm_id VARCHAR(255),
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

ALTER TABLE venues ADD COLUMN location geography (POINT, 4326);

CREATE UNIQUE INDEX venues_external_tm_id_unique ON venues (external_tm_id) WHERE external_tm_id IS NOT NULL;

CREATE TABLE events (
    id BIGSERIAL PRIMARY KEY,
    external_tm_id VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    hold_ttl_seconds SMALLINT NOT NULL DEFAULT 240,
    venue_id BIGINT NOT NULL REFERENCES venues (id) ON DELETE CASCADE,
    starts_at TIMESTAMP WITH TIME ZONE NOT NULL,
    hidden_at TIMESTAMP WITH TIME ZONE,
    category VARCHAR(255),
    seat_layout JSONB,
    tm_sync_paused BOOLEAN NOT NULL DEFAULT FALSE,
    tm_url VARCHAR(1024),
    price DECIMAL(10, 2),
    image_url VARCHAR(1024),
    description TEXT,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX events_external_tm_id_index ON events (external_tm_id);

CREATE TABLE zones (
    id BIGSERIAL PRIMARY KEY,
    event_id BIGINT NOT NULL REFERENCES events (id) ON DELETE CASCADE,
    external_zone_key VARCHAR(255),
    label VARCHAR(255) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE TABLE seats (
    id BIGSERIAL PRIMARY KEY,
    event_id BIGINT NOT NULL REFERENCES events (id) ON DELETE CASCADE,
    zone_id BIGINT NOT NULL REFERENCES zones (id) ON DELETE CASCADE,
    external_seat_key VARCHAR(255) NOT NULL,
    status VARCHAR(255) NOT NULL DEFAULT 'available',
    current_hold_id UUID,
    held_until TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    UNIQUE (event_id, external_seat_key)
);

CREATE INDEX seats_status_index ON seats (status);
CREATE INDEX seats_event_id_status_index ON seats (event_id, status);

CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    event_id BIGINT NOT NULL REFERENCES events (id) ON DELETE CASCADE,
    hold_uuid UUID UNIQUE,
    state VARCHAR(255) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    total_amount DECIMAL(12, 2),
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX orders_state_index ON orders (state);

CREATE TABLE order_lines (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders (id) ON DELETE CASCADE,
    seat_id BIGINT REFERENCES seats (id) ON DELETE CASCADE,
    seat_key VARCHAR(255),
    unit_price DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    UNIQUE (order_id, seat_id)
);

CREATE TABLE tickets (
    id UUID PRIMARY KEY,
    public_uuid UUID NOT NULL UNIQUE,
    order_line_id BIGINT NOT NULL UNIQUE REFERENCES order_lines (id) ON DELETE CASCADE,
    status VARCHAR(255) NOT NULL,
    qr_payload_ref VARCHAR(255),
    jwt_expires_at TIMESTAMP WITH TIME ZONE,
    used_at TIMESTAMP WITH TIME ZONE,
    validator_id BIGINT REFERENCES users (id) ON DELETE SET NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX tickets_status_index ON tickets (status);

CREATE TABLE saved_events (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    event_id BIGINT NOT NULL REFERENCES events (id) ON DELETE CASCADE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    UNIQUE (user_id, event_id)
);

CREATE TABLE user_settings (
    user_id BIGINT PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE TABLE friend_invites (
    id UUID PRIMARY KEY,
    sender_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    receiver_id BIGINT REFERENCES users (id) ON DELETE SET NULL,
    receiver_email VARCHAR(255),
    status VARCHAR(255) NOT NULL DEFAULT 'pending',
    invite_token VARCHAR(255) UNIQUE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX friend_invites_status_index ON friend_invites (status);

CREATE TABLE ticket_transfers (
    id BIGSERIAL PRIMARY KEY,
    ticket_id UUID NOT NULL REFERENCES tickets (id) ON DELETE CASCADE,
    from_user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    to_user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    status VARCHAR(255) NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX ticket_transfers_status_index ON ticket_transfers (status);

-- Notificacions socials (feed G/H): esdeveniments i entrades compartides; nom social_notifications per evitar col·lisió amb Notifiable de Laravel.
CREATE TABLE social_notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    actor_user_id BIGINT REFERENCES users (id) ON DELETE SET NULL,
    type VARCHAR(64) NOT NULL,
    payload JSONB NOT NULL DEFAULT '{}',
    read_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX social_notifications_user_created_index ON social_notifications (user_id, created_at DESC);

-- Silenci de toasts per fil compartit amb un amic (el xat segueix en temps real).
CREATE TABLE social_thread_notification_mutes (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    peer_user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    UNIQUE (user_id, peer_user_id)
);

CREATE INDEX social_thread_notification_mutes_user_index ON social_thread_notification_mutes (user_id);

CREATE TABLE tm_discovery_sync (
    id BIGSERIAL PRIMARY KEY,
    cursor TEXT,
    last_run_at TIMESTAMP WITH TIME ZONE,
    last_error TEXT,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

-- ---------------------------------------------------------------------------
-- Spatie Laravel Permission (sense equips)
-- ---------------------------------------------------------------------------

CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    UNIQUE (name, guard_name)
);

CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    UNIQUE (name, guard_name)
);

CREATE TABLE model_has_permissions (
    permission_id BIGINT NOT NULL REFERENCES permissions (id) ON DELETE CASCADE,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type)
);

CREATE INDEX model_has_permissions_model_id_model_type_index ON model_has_permissions (model_id, model_type);

CREATE TABLE model_has_roles (
    role_id BIGINT NOT NULL REFERENCES roles (id) ON DELETE CASCADE,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type)
);

CREATE INDEX model_has_roles_model_id_model_type_index ON model_has_roles (model_id, model_type);

CREATE TABLE role_has_permissions (
    permission_id BIGINT NOT NULL REFERENCES permissions (id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES roles (id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);

-- Auditoria panell administració
CREATE TABLE admin_logs (
    id BIGSERIAL PRIMARY KEY,
    admin_user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(120) NOT NULL,
    entity_id BIGINT,
    summary TEXT NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ
);

CREATE INDEX admin_logs_created_at_index ON admin_logs (created_at DESC);
CREATE INDEX admin_logs_admin_user_id_index ON admin_logs (admin_user_id);
