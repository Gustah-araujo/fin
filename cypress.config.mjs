import { defineConfig } from 'cypress';

export default defineConfig({
    e2e: {
        baseUrl: 'http://localhost:8090',
        supportFile: false,
        specPattern: 'cypress/e2e/**/*.cy.{ts,js}',
        experimentalRunAllSpecs: true,
        viewportWidth: 1280,
        viewportHeight: 800,
    },
});
