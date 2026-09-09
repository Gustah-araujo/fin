const MAILPIT_API = `http://localhost:${Cypress.env('MAILPIT_PORT') || '8026'}/api/v1`;

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
            `${MAILPIT_API}/search?kind=to&query=${encodeURIComponent(email)}`
        )
        .then((resp) => {
            const msg = (resp.body.messages || [])[0];
            if (!msg) throw new Error(`No verification email found for ${email}`);
            return cy.request(`${MAILPIT_API}/message/${msg.ID}`);
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

Cypress.Commands.add('assertToast', (expectedType = 'success', expectedMessage = null) => {
    cy.window().then((win) => {
        return new Cypress.Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                win.removeEventListener('toast', handler);
                reject(new Error(`Toast of type "${expectedType}" not found within 5s`));
            }, 5000);

            const handler = (event) => {
                const { type, message } = event.detail;
                if (type === expectedType && (!expectedMessage || message.includes(expectedMessage))) {
                    clearTimeout(timeout);
                    win.removeEventListener('toast', handler);
                    resolve();
                }
            };
            win.addEventListener('toast', handler);
        });
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
