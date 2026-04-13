/**
 * Verifica que existeixin les carpetes del monorepo (T001 Speckit).
 * Execució: node scripts/verify-monorepo-structure.js
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

const requiredDirs = [
  'backend-api',
  'frontend-nuxt',
  'socket-server',
  'database',
  'docker/dev',
  'docker/prod',
  'docker/dockerfiles/backend-api',
  'docker/dockerfiles/frontend-nuxt',
  'docker/dockerfiles/socket-server',
  'docs',
];

let failed = false;
for (let i = 0; i < requiredDirs.length; i++) {
  const rel = requiredDirs[i];
  const abs = path.join(root, rel);
  if (!fs.existsSync(abs)) {
    failed = true;
    console.error('Falta el directori:', rel);
  }
}

if (failed) {
  process.exit(1);
}

console.log('verify-monorepo-structure: OK (' + requiredDirs.length + ' directoris)');
process.exit(0);
