# Imatge dev — Node 20 + Socket.IO / Redis / qrcode
FROM node:20-bookworm-slim

WORKDIR /app

COPY socket-server/package.json ./
RUN npm install

COPY socket-server/ ./

EXPOSE 3001
