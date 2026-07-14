Cypress.Commands.add('register', (email, name = 'Test User', password = 'password123') => {
    cy.visit('/register');
    cy.get('#name').type(name);
    cy.get('#email').type(email);
    cy.get('#password').type(password);
    cy.get('#password_confirmation').type(password);
    cy.get('button[type="submit"]').click();
});

Cypress.Commands.add('getVerificationLink', (email) => {
    return cy
        .request(
            `http://localhost:8026/api/v1/search?kind=to&query=${encodeURIComponent(email)}`
        )
        .then((resp) => {
            const msg = (resp.body.messages || [])[0];
            if (!msg) throw new Error(`No verification email found for ${email}`);
            return cy.request(`http://localhost:8026/api/v1/message/${msg.ID}`);
        })
        .then((resp) => {
            const html = resp.body.HTML || resp.body.Text || '';
            const match = html.match(/href="([^"]*verify-email[^"]*)"/i);
            if (!match) throw new Error('Verification link not found in email body');
            return match[1].replace(/&amp;/g, '&');
        });
});

Cypress.Commands.add('loginViaSession', (sessionId) => {
    cy.session(sessionId, () => {
        const email = `e2e-${Date.now()}@fin.test`;

        cy.visit('/register');
        cy.get('#name').type('E2E Test User');
        cy.get('#email').type(email);
        cy.get('#password').type('password123');
        cy.get('#password_confirmation').type('password123');
        cy.get('button[type="submit"]').click();

        cy.wait(1000);

        cy.getVerificationLink(email).then((verifyUrl) => {
            cy.visit(verifyUrl);
        });
        cy.url().should('match', /\/workspace/);
    }, {
        validate() {
            cy.visit('/workspace/create');
            cy.url().should('not.include', '/login');
        }
    });
});

Cypress.Commands.add('registerAndCreateWorkspace', (workspaceName = 'E2E Workspace') => {
    const email = `e2e-${Date.now()}@example.com`;

    cy.register(email);

    cy.wait(1000);

    return cy.getVerificationLink(email).then((verifyUrl) => {
        cy.visit(verifyUrl);
        cy.url().should('include', '/workspace');
        cy.get('#name').type(workspaceName);
        cy.get('button[type="submit"]').click();
        cy.url().should('match', /\/w\/([a-f0-9-]+)/);

        return cy.url().then((url) => {
            const uuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
            return uuid;
        });
    });
});
