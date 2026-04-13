# Docker — producció amb Nginx (reverse proxy al port 80)

Els Dockerfiles exposen **Nuxt 3000**, **Laravel 8000**, **Socket 3001**. En producció el navegador ha d’usar **només** `http://LA_TEVA_IP/` (port 80): Nginx fa de pont cap als tres serveis.

## Variables del frontend (mateix origen, sense ports al navegador)

Abans de **construir** o arrencar Nuxt amb aquestes URLs (substitueix la IP si cal):

```env
NUXT_PUBLIC_API_URL=http://89.167.124.133
NUXT_PUBLIC_SOCKET_URL=http://89.167.124.133
```

Sense barra final. El client crida `http://89.167.124.133/api/...` i Nginx envia `/api/` al contenidor de Laravel.

## 1. Servidor (Ubuntu/Debian): dependències

```bash
sudo apt update
sudo apt install -y git nginx docker.io docker-compose-plugin
sudo systemctl enable nginx --now
sudo usermod -aG docker "$USER"
# Tanca sessió i torna a entrar perquè el grup docker s’apliqui.
```

## 2. Codi i entorn

```bash
cd /opt   # o la carpeta que vulguis
sudo git clone https://github.com/TU_ORG/TU_REPO.git entrades
sudo chown -R "$USER:$USER" entrades
cd entrades/prj-entrades-nou

cp backend-api/.env.example backend-api/.env
cp frontend-nuxt/.env.example frontend-nuxt/.env
cp socket-server/.env.example socket-server/.env
```

Edita `frontend-nuxt/.env` amb les variables públiques d’adalt. Revisa secrets a `backend-api/.env` i alineació amb el compose (JWT, DB, etc.).

## 3. Docker només a localhost (recomanat amb Nginx al 80)

Els serveis han d’escoltar a **127.0.0.1** perquè només Nginx sigui accessible des de fora. Opcions:

- **A)** Edita `docker/dev/docker-compose.yml` i canvia cada `ports:` per exemple de `'3000:3000'` a `'127.0.0.1:3000:3000'` (i igual per 8000 i 3001), o  
- **B**) Mantén els ports oberts només si el firewall ja bloqueja 3000/8000/3001 des de fora (`ufw`).

## 4. Arrencar el stack

Des de l’arrel `prj-entrades-nou`:

```bash
docker compose -f docker/dev/docker-compose.yml build
docker compose -f docker/dev/docker-compose.yml up -d
```

Comprova que responguin:

```bash
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/api/health
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/
```

## 5. Nginx (fitxer del repositori)

```bash
cd /opt/entrades/prj-entrades-nou   # ajusta el camí

sudo cp docker/prod/nginx/host-entrades.conf /etc/nginx/sites-available/entrades
sudo ln -sf /etc/nginx/sites-available/entrades /etc/nginx/sites-enabled/

# Evita conflicte amb el lloc per defecte (opcional)
# sudo rm -f /etc/nginx/sites-enabled/default

sudo nginx -t
sudo systemctl reload nginx
```

El fitxer `host-entrades.conf` usa `server_name 89.167.124.133;`. Si tens domini, edita aquesta línia o duplica el `server` block.

## 6. Tallafocs

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
# o només HTTP: sudo ufw allow 80/tcp
sudo ufw enable
sudo ufw status
```

Assegura’t que **Docker** no exposi 3000/8000/3001 a `0.0.0.0` si vols que només Nginx sigui la porta d’entrada (vegeu pas 3).

## 7. Prova des del navegador

Obre **http://89.167.124.133/** (sense port: és el 80).

Si la pàgina no carrega: comprova `docker compose ps`, logs (`docker compose logs -f`), i `sudo tail -f /var/log/nginx/error.log`.

## Laravel darrere de proxy

Configura `APP_URL=http://89.167.124.133` (o HTTPS quan tinguis certificat) a `backend-api/.env`. Si hi ha problemes amb URLs o cookies, revisa la documentació de Laravel sobre **trusted proxies** i `TrustProxies`.
