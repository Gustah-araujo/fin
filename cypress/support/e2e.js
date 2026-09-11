import './commands';

// Suppress ResizeObserver loop errors from Radix UI components.
// These are benign — they fire when multiple resize events occur
// within a single animation frame and don't affect functionality.
beforeEach(() => {
    cy.on('uncaught:exception', (err) => {
        if (err.message.includes('ResizeObserver loop')) {
            return false;
        }
    });
});
