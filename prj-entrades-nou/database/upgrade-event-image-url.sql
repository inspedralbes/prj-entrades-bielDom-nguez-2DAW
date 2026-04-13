-- URL de pòster Ticketmaster (Discovery `images[].url`, pública HTTPS)
ALTER TABLE events ADD COLUMN IF NOT EXISTS image_url VARCHAR(1024);
