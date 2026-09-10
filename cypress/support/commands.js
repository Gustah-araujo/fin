const MAILPIT_API = `http://localhost:${Cypress.env('MAILPIT_PORT') || '8026'}/api/v1`;

Cypress.Commands.add(
    'register',
    (email, name = 'Test User', password = 'password123') => {
        cy.visit('/register');
        cy.get('#name').type(name);
        cy.get('#email').type(email);
        cy.get('#password').type(password);
        cy.get('#password_confirmation').type(password);
        cy.get('button[type="submit"]').click();
    },
);

Cypress.Commands.add('getVerificationLink', (email) => {
    return cy
        .request(
            `${MAILPIT_API}/search?kind=to&query=${encodeURIComponent(email)}`,
        )
        .then((resp) => {
            const msg = (resp.body.messages || [])[0];
            if (!msg)
                throw new Error(`No verification email found for ${email}`);
            return cy.request(`${MAILPIT_API}/message/${msg.ID}`);
        })
        .then((resp) => {
            const html = resp.body.HTML || resp.body.Text || '';
            const match = html.match(/href="([^"]*verify-email[^"]*)"/i);
            if (!match)
                throw new Error('Verification link not found in email body');
            return match[1].replace(/&amp;/g, '&');
        });
});

/**
 * Poll Mailpit until the verification email arrives, then return the link.
 *
 * Retries on ALL failure modes:
 *   - Network errors (Mailpit container unavailable, connection refused)
 *   - HTTP errors (500/503 under CI load)
 *   - Email not yet delivered
 *   - Link not yet parseable from email body
 *
 * Uses Cypress.Promise so we can safely catch request failures and retry,
 * which is NOT possible with .catch() directly on a Cypress chainable.
 * Fixes flakiness caused by Mailpit being slow or transiently unavailable under CI load.
 */
Cypress.Commands.add('waitForVerificationLink', (email, timeoutMs = 15000) => {
    const start = Date.now();

    const extractLink = (html) => {
        const match = html.match(/href="([^"]*verify-email[^"]*)"/i);
        return match ? match[1].replace(/&amp;/g, '&') : null;
    };

    const retry = (resolve, reject) => {
        if (Date.now() - start > timeoutMs) {
            return reject(
                new Error(
                    `Verification email for ${email} not received within ${timeoutMs}ms`,
                ),
            );
        }
        attempt()
            .then(resolve)
            .catch(() => setTimeout(() => retry(resolve, reject), 300));
    };

    const attempt = () => {
        return new Cypress.Promise((resolve, reject) => {
            cy.request({
                url: `${MAILPIT_API}/search?kind=to&query=${encodeURIComponent(email)}`,
                failOnStatusCode: false,
                timeout: 5000,
            })
                .then((resp) => {
                    const msg =
                        resp.status === 200 && (resp.body.messages || [])[0];

                    if (!msg) {
                        return reject(new Error('No email yet'));
                    }

                    cy.request({
                        url: `${MAILPIT_API}/message/${msg.ID}`,
                        failOnStatusCode: false,
                        timeout: 5000,
                    })
                        .then((msgResp) => {
                            if (msgResp.status !== 200) {
                                return reject(
                                    new Error('Failed to fetch email body'),
                                );
                            }

                            const html =
                                msgResp.body.HTML || msgResp.body.Text || '';
                            const link = extractLink(html);

                            if (!link) {
                                return reject(
                                    new Error('No link in email body'),
                                );
                            }

                            resolve(link);
                        })
                        .catch(() =>
                            reject(new Error('Message request failed')),
                        );
                })
                .catch(() => reject(new Error('Search request failed')));
        });
    };

    return new Cypress.Promise((resolve, reject) => {
        retry(resolve, reject);
    });
});

Cypress.Commands.add('loginViaSession', (sessionId) => {
    cy.session(
        sessionId,
        () => {
            const email = `e2e-${Date.now()}@fin.test`;

            cy.visit('/register');
            cy.get('#name').type('E2E Test User');
            cy.get('#email').type(email);
            cy.get('#password').type('password123');
            cy.get('#password_confirmation').type('password123');
            cy.get('button[type="submit"]').click();

            cy.waitForVerificationLink(email).then((verifyUrl) => {
                cy.visit(verifyUrl);
            });
            cy.url().should('match', /\/workspace/);
        },
        {
            validate() {
                cy.visit('/workspace/create');
                cy.url().should('not.include', '/login');
            },
        },
    );
});

Cypress.Commands.add(
    'assertToast',
    (expectedType = 'success', expectedMessage = null) => {
        cy.window().then((win) => {
            // Check the buffer first — catches toasts dispatched before listener attaches.
            // This fixes the race condition where Inertia SPA navigation fires the event
            // before Cypress can register its listener.
            const buffer = win.__toastBuffer || [];
            const alreadyDispatched = buffer.find(
                (t) =>
                    t.type === expectedType &&
                    (!expectedMessage || t.message.includes(expectedMessage)),
            );
            if (alreadyDispatched) {
                return; // Toast was already dispatched — assertion passes immediately.
            }

            // Toast not yet dispatched — listen for it.
            return new Cypress.Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    win.removeEventListener('toast', handler);
                    reject(
                        new Error(
                            `Toast of type "${expectedType}" not found within 5s`,
                        ),
                    );
                }, 5000);

                const handler = (event) => {
                    const { type, message } = event.detail;
                    if (
                        type === expectedType &&
                        (!expectedMessage || message.includes(expectedMessage))
                    ) {
                        clearTimeout(timeout);
                        win.removeEventListener('toast', handler);
                        resolve();
                    }
                };
                win.addEventListener('toast', handler);
            });
        });
    },
);

Cypress.Commands.add(
    'registerAndCreateWorkspace',
    (workspaceName = 'E2E Workspace') => {
        const email = `e2e-${Date.now()}@example.com`;

        cy.register(email);

        return cy.waitForVerificationLink(email).then((verifyUrl) => {
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
    },
);
