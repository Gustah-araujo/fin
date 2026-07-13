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

        // Get verification link from Mailpit
        cy.request('http://localhost:8026/api/v1/messages').then((resp) => {
            const messages = resp.body.messages;
            const latestMsg = messages.find((m) =>
                m.To.some((t) => t.Address === email)
            );
            expect(latestMsg, 'verification email found').to.exist;
            return cy.request(`http://localhost:8026/api/v1/message/${latestMsg.ID}`);
        }).then((resp) => {
            const html = resp.body.HTML || resp.body.Text || '';
            const match = html.match(/href="([^"]*verify-email[^"]*)"/i);
            expect(match, 'verification link found').to.not.be.null;
            return match[1].replace(/&amp;/g, '&');
        }).then((verifyUrl) => {
            // Click verification link
            cy.visit(verifyUrl);
        });

        // Now verified, should redirect to workspace create
        cy.url().should('include', '/workspace/create');
        cy.get('#name').type('Meu Workspace');
        cy.get('button[type="submit"]').click();
        cy.url().should('match', /\/w\/[a-f0-9-]+/);
        cy.contains('Dashboard').should('be.visible');
    });
});
