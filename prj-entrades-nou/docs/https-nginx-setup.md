# Configurar HTTPS amb Nginx (Certbot)

Guia pas a pas per activar HTTPS al teu servidor utilitzant Nginx com a reverse proxy i Certbot per obtenir certificates SSL gratuïts.

## Requisits previs

- Nginx instal·lat al servidor
- Accés per terminal (SSH) com a root o usuari amb sudo
- El domini apuntant al teu servidor (DNS configurat)
- Els serveis funcionant: Nuxt (port 3000), API (port 8000), Socket (port 3001)

## Passos

### 1. Instal·lar Certbot

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install certbot python3-certbot-nginx

# Verificar instal·lació
certbot --version
```

### 2. Obrir els ports al firewall

```bash
# Si tens ufw actiu
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable       # Si no està actiu
```

### 3. Configurar Nginx per al teu domini

Crea un fitxer de configuració:

```bash
sudo nano /etc/nginx/sites-available/pulse.daw.inspedralbes.cat
```

Contingut del fitxer:

```nginx
server {
    listen 80;
    server_name pulse.daw.inspedralbes.cat;

    # Redirigir HTTP → HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name pulse.daw.inspedralbes.cat;

    # SSL Certificate (vegeu pas 4)
    ssl_certificate /etc/letsencrypt/live/pulse.daw.inspedralbes.cat/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/pulse.daw.inspedralbes.cat/privkey.pem;

    # Seguretat SSL
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_session_tickets off;

    # Protocols segurs (desactivar TLS 1.2 i anteriors)
    ssl_protocols TLSv1.3 TLSv1.2;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;

    # HSTS (opcional)
    add_header Strict-Transport-Security "max-age=63072000" always;

    # Frontend Nuxt (port 3000)
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Socket.IO WebSocket (port 3001)
    location /socket.io/ {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # API Laravel (port 8000)
    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 4. Activar el lloc i obtenir el certificat

```bash
# Crear enllaç simbolic
sudo ln -s /etc/nginx/sites-available/pulse.daw.inspedralbes.cat /etc/nginx/sites-enabled/

# Verificar configuració Nginx
sudo nginx -t

# Reinciar Nginx
sudo systemctl reload nginx

# Obtenir certificat SSL (automàtic)
sudo certbot --nginx -d pulse.daw.inspedralbes.cat
```

Segueix les instruccions de Certbot:
- Introdueix el teu email (per renovacions)
- Accepta els Termes de Servei
- Escull si vols redirect HTTP → HTTPS (recomanat: 1 per redirect)

### 5. Verificar que funciona

```bash
# Test HTTPS
curl -I https://pulse.daw.inspedralbes.cat

# Test WebSocket
curl -I wss://pulse.daw.inspedralbes.cat/socket.io/?EIO=4
```

### 6. Actualizar variables d'entorn per producció

Al teu fitxer `.env` de producció:

```bash
NUXT_PUBLIC_SOCKET_URL=wss://pulse.daw.inspedralbes.cat
NUXT_PUBLIC_API_URL=https://pulse.daw.inspedralbes.cat/api
```

(Canvia `http://` per `https://` i `ws://` per `wss://`)

---

## Renovació automàtica

Certbot renova automàticament els certificats. Verificar:

```bash
# Verificar renovació automàtica
sudo certbot renew --dry-run

# Renovació manual (si cal)
sudo certbot renew
```

Per a renovació, pots afegir un cron:

```bash
sudo crontab -e
```

Afegir:
```
0 0 * * * certbot renew --quiet --post-hook "systemctl reload nginx"
```

---

## Resum de URLs

| Servei | HTTP (desactivar!) | HTTPS |
|--------|------------------|------|
| Frontend | http://pulse.daw.inspedralbes.cat:3000 | https://pulse.daw.inspedralbes.cat |
| API | http://pulse.daw.inspedralbes.cat:8000 | https://pulse.daw.inspedralbes.cat/api |
| Socket | ws://pulse.daw.inspedralbes.cat:3001 | wss://pulse.daw.inspedralbes.cat/socket.io/ |