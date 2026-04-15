-- Text llarg per al detall públic de l’esdeveniment (opcional).
ALTER TABLE events ADD COLUMN IF NOT EXISTS description TEXT;
