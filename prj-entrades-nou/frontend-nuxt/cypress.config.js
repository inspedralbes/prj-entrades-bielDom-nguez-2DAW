import { defineConfig } from 'cypress';
import path from 'path';
import fs from 'fs';
import { execSync } from 'child_process';

export default defineConfig({
  e2e: {
    setupNodeEvents(on, config) {
      on('task', {
        seedCypressOrderFlow() {
          const backendRoot = path.join(process.cwd(), '..', 'backend-api');
          execSync('php artisan db:seed --class=CypressOrderFlowSeeder', {
            cwd: backendRoot,
            stdio: 'inherit',
            env: { ...process.env },
          });
          const demoPath = path.join(backendRoot, 'storage', 'app', 'cypress-order-demo.json');
          if (!fs.existsSync(demoPath)) {
            throw new Error(`Esperat ${demoPath} després del seeder`);
          }
          return JSON.parse(fs.readFileSync(demoPath, 'utf8'));
        },
      });
      return config;
    },
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost:3000',
    supportFile: 'cypress/support/e2e.js',
    specPattern: 'cypress/e2e/**/*.cy.js',
    env: {
      apiUrl: process.env.CYPRESS_API_URL || 'http://localhost:8000',
      socketUrl: process.env.CYPRESS_SOCKET_URL || 'http://localhost:3001',
    },
  },
});
