import { defineConfig } from 'cypress';

export default defineConfig({
    e2e: {
        baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost:8000',
        supportFile: 'cypress/support/e2e.js',
        specPattern: 'cypress/e2e/**/*.cy.{ts,js}',
        experimentalRunAllSpecs: true,
        viewportWidth: 1280,
        viewportHeight: 800,
    },
    env: {
        MAILPIT_PORT: process.env.CYPRESS_MAILPIT_PORT || '8026',
    },
});
