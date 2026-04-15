# Imatge dev — Node 20 + Socket.IO / Redis / qrcode
# Espeig public.ecr.aws (mateixa imatge que library/node; menys pulls des de docker.io).
FROM public.ecr.aws/docker/library/node:20-bookworm-slim

WORKDIR /app

COPY socket-server/package.json ./
RUN npm install

COPY socket-server/ ./

EXPOSE 3001
