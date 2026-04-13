-- Aplicar manualment a PostgreSQL si la BD ja existia abans del canvi social_notifications.
CREATE TABLE IF NOT EXISTS social_notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    actor_user_id BIGINT REFERENCES users (id) ON DELETE SET NULL,
    type VARCHAR(64) NOT NULL,
    payload JSONB NOT NULL DEFAULT '{}',
    read_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS social_notifications_user_created_index ON social_notifications (user_id, created_at DESC);
