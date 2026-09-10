const MAILPIT_API = `http://localhost:${Cypress.env('MAILPIT_PORT') || '8026'}/api/v1`;

describe('Login', () => {
    it('shows error on invalid credentials', () => {
        cy.visit('/login');

        cy.get('#email').type('wrong@email.com');
        cy.get('#password').type('wrongpassword');
        cy.get('button[type="submit"]').click();

        cy.contains('Credenciais inválidas').should('be.visible');
    });

    it('registers, verifies email via mailpit, then creates workspace', () => {
        const email = `e2e-${Date.now()}@example.com`;

        // Register
        cy.visit('/register');
        cy.get('#name').type('E2E User');
        cy.get('#email').type(email);
        cy.get('#password').type('password123');
        cy.get('#password_confirmation').type('password123');
        cy.get('button[type="submit"]').click();

        // After registration we are logged in and on verify-email
        cy.url().should('include', '/verify-email');
        cy.contains('Verifique seu email').should('be.visible');

        // Get verification link from Mailpit (retries until email arrives)
        cy.waitForVerificationLink(email).then((verifyUrl) => {
            cy.visit(verifyUrl);
        });

        // Now verified, should redirect to workspace create
        cy.url().should('include', '/workspace/create');
        cy.get('#name').type('Meu Workspace');
        cy.get('button[type="submit"]').click();
        cy.assertToast('success', 'criado');
        cy.url().should('match', /\/w\/[a-f0-9-]+/);
        cy.contains('Dashboard').should('be.visible');
    });
});
