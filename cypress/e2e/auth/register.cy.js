describe('Registration', () => {
    it('registers and lands on verify email screen', () => {
        cy.visit('/register');

        cy.get('#name').type('Test User');
        cy.get('#email').type(`e2e-register-${Date.now()}@example.com`);
        cy.get('#password').type('password123');
        cy.get('#password_confirmation').type('password123');
        cy.get('button[type="submit"]').click();

        cy.url().should('include', '/verify-email');
        cy.contains('Verifique seu email').should('be.visible');
    });

    it('shows validation errors on invalid input', () => {
        cy.visit('/register');
        cy.get('button[type="submit"]').click();

        cy.contains('O nome é obrigatório').should('be.visible');
        cy.contains('O email é obrigatório').should('be.visible');
    });

    it('rejects duplicate email', () => {
        const email = `e2e-dup-${Date.now()}@example.com`;

        // First registration
        cy.visit('/register');
        cy.get('#name').type('First User');
        cy.get('#email').type(email);
        cy.get('#password').type('password123');
        cy.get('#password_confirmation').type('password123');
        cy.get('button[type="submit"]').click();

        cy.url().should('include', '/verify-email');

        // Logout via "Sair" button
        cy.contains('Sair').click();
        cy.url().should('include', '/login');

        // Try registering with same email
        cy.visit('/register');
        cy.get('#name').type('Second User');
        cy.get('#email').type(email);
        cy.get('#password').type('password123');
        cy.get('#password_confirmation').type('password123');
        cy.get('button[type="submit"]').click();

        cy.contains('já está em uso').should('be.visible');
    });
});
