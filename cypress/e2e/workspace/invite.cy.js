const MAILPIT_API = `http://localhost:${Cypress.env('MAILPIT_PORT') || '8026'}/api/v1`;

describe('Workspace Invites', () => {
    it('two users register and create workspaces', () => {
        const adminEmail = `e2e-admin-${Date.now()}@example.com`;
        const memberEmail = `e2e-member-${Date.now()}@example.com`;

        cy.visit('/register');
        cy.get('#name').type('Admin');
        cy.get('#email').type(adminEmail);
        cy.get('#password').type('password123');
        cy.get('#password_confirmation').type('password123');
        cy.get('button[type="submit"]').click();

        // Wait for email, then search Mailpit
        cy.wait(1000);
        cy.request(`${MAILPIT_API}/search?kind=to&query=${encodeURIComponent(adminEmail)}`).then((resp) => {
            const msg = (resp.body.messages || [])[0];
            if (!msg) throw new Error(`No message for ${adminEmail}`);
            return cy.request(`${MAILPIT_API}/message/${msg.ID}`);
        }).then((resp) => {
            const html = resp.body.HTML || resp.body.Text || '';
            const match = html.match(/href="([^"]*verify-email[^"]*)"/i);
            cy.visit(match[1].replace(/&amp;/g, '&'));
        });

        cy.url().should('include', '/workspace');
        cy.get('#name').type('Workspace Admin');
        cy.get('button[type="submit"]').click();
        cy.assertToast('success', 'criado');
        cy.url().should('match', /\/w\/[a-f0-9-]+/);

        cy.clearCookies();
        cy.visit('/register');
        cy.get('#name').type('Member');
        cy.get('#email').type(memberEmail);
        cy.get('#password').type('password123');
        cy.get('#password_confirmation').type('password123');
        cy.get('button[type="submit"]').click();
        cy.url().should('include', '/verify-email');
    });
});
