-- Upgrade script: spain-catalog-feed2-proximity-quantity
-- Adds TM fields to events and quantity to orders

-- 1. Add TM fields to events table
ALTER TABLE events ADD COLUMN tm_url VARCHAR(500);
ALTER TABLE events ADD COLUMN tm_category VARCHAR(100);
ALTER TABLE events ADD COLUMN is_large_event BOOLEAN DEFAULT FALSE;

-- 2. Add quantity to orders table
ALTER TABLE orders ADD COLUMN quantity INTEGER;

-- 3. Make seat_id nullable in order_lines (for quantity purchases)
ALTER TABLE order_lines ALTER COLUMN seat_id DROP NOT NULL;

-- 4. Create index on events for tm_category filter
CREATE INDEX events_tm_category_index ON events (tm_category);
CREATE INDEX events_is_large_event_index ON events (is_large_event);

-- 5. Create index on orders for quantity
CREATE INDEX orders_quantity_index ON orders (quantity);