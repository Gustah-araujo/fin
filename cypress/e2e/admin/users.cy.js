const MAILPIT_API = `http://localhost:${
    Cypress.env('MAILPIT_PORT') || '8025'
}/api/v1`;

function mailpitMessageCount(email) {
    return cy
        .request(
            `${MAILPIT_API}/search?kind=to&query=${encodeURIComponent(email)}`,
        )
        .then((resp) => (resp.body.messages || []).length);
}

/**
 * Poll Mailpit until at least `minimumCount` messages exist for `email`.
 * Uses recursive cy.then() chaining (same pattern as waitForVerificationLink)
 * so retries stay inside Cypress's command queue.
 */
function waitForMailpitMessages(email, minimumCount, timeoutMs = 30000) {
    const start = Date.now();

    const attempt = () => {
        if (Date.now() - start > timeoutMs) {
            throw new Error(
                `Mailpit did not reach ${minimumCount} message(s) for ${email} within ${timeoutMs}ms`,
            );
        }

        return cy
            .request({
                url: `${MAILPIT_API}/search?kind=to&query=${encodeURIComponent(
                    email,
                )}`,
                failOnStatusCode: false,
                timeout: 10000,
            })
            .then((resp) => {
                const count =
                    resp.status === 200 ? (resp.body.messages || []).length : 0;

                if (count < minimumCount) {
                    return cy.wait(1000).then(() => attempt());
                }

                return count;
            });
    };

    return attempt();
}

function filterUnverified() {
    // Status column select in the filter row (only select filter in the table).
    cy.get('table thead tr')
        .eq(1)
        .find('[data-slot="select-trigger"]')
        .first()
        .click();
    cy.contains('[role="option"]', 'Não Verificados').click();
    cy.wait('@datatable');
}

describe('Admin Users', () => {
    const email = `e2e-admin-users-${Date.now()}@example.com`;

    before(() => {
        // Register (UI) a user and leave it unverified on purpose.
        cy.register(email);
        cy.url().should('include', '/verify-email');

        cy.createAdminSession();
    });

    beforeEach(() => {
        cy.createAdminSession();
        cy.intercept('GET', '**/admin/users.datatable*').as('datatable');
        cy.visit('/admin/users');
        cy.wait('@datatable');
    });

    // Smoke test: users index renders the table.
    it('renders the users table without crashing', () => {
        cy.get('table').should('be.visible');
        cy.get('table tbody tr').should('have.length.greaterThan', 0);
    });

    it('resends the verification email to an unverified user (toast + Mailpit)', () => {
        mailpitMessageCount(email).then((beforeCount) => {
            filterUnverified();

            cy.contains('table tbody tr', email).as('row');
            cy.get('@row')
                .find('[data-testid="admin-user-status-pending"]')
                .should('be.visible');
            cy.get('@row')
                .find('[data-testid="admin-user-actions-trigger"]')
                .click();
            cy.get('[data-testid="admin-user-resend-verification"]').click();

            cy.assertToast('success', 'reenviado');

            // The registration email already counts as beforeCount; the
            // admin resend must add one more message in Mailpit.
            waitForMailpitMessages(email, beforeCount + 1);
        });
    });

    it('marks an unverified user as verified manually (toast + status updates)', () => {
        filterUnverified();

        cy.contains('table tbody tr', email).as('row');
        cy.get('@row')
            .find('[data-testid="admin-user-actions-trigger"]')
            .click();
        cy.get('[data-testid="admin-user-manual-verify"]').click();

        cy.assertToast('success', 'marcado como verificado');

        // Table reloads; the user leaves the "unverified" list.
        cy.wait('@datatable');
        cy.get('table tbody').should('not.contain', email);

        // Switch the filter to verified and check the badge.
        cy.get('table thead tr')
            .eq(1)
            .find('[data-slot="select-trigger"]')
            .first()
            .click();
        cy.contains('[role="option"]', /^Verificados$/).click();
        cy.wait('@datatable');

        cy.contains('table tbody tr', email)
            .find('[data-testid="admin-user-status-verified"]')
            .should('be.visible');
    });

    it('does not show the actions dropdown for verified users', () => {
        // The user verified in the previous test is the most recent one,
        // so it sits on top of the default (created_at desc) ordering.
        cy.contains('table tbody tr', email).as('row');
        cy.get('@row')
            .find('[data-testid="admin-user-status-verified"]')
            .should('be.visible');
        cy.get('@row')
            .find('[data-testid="admin-user-actions-trigger"]')
            .should('not.exist');
    });
});
