describe('Admin Cross Navigation', () => {
    const email = `e2e-admin-cross-${Date.now()}@example.com`;
    const workspaceName = 'E2E Admin Cross';
    let workspaceUuid;
    let userUuid;

    before(() => {
        // Register (UI) a verified user that owns a workspace.
        cy.register(email);
        cy.url().should('include', '/verify-email');

        cy.waitForVerificationLink(email).then((verifyUrl) => {
            cy.visit(verifyUrl);
        });
        cy.url().should('include', '/workspace/create');
        cy.get('#name').type(workspaceName);
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });

        cy.createAdminSession();
    });

    beforeEach(() => {
        cy.createAdminSession();
        cy.intercept('GET', '**/admin/users.datatable*').as('datatable');
        cy.visit('/admin/users');
        cy.wait('@datatable');
    });

    // Smoke test: /admin renders without crashing.
    it('renders the admin dashboard without crashing', () => {
        cy.visit('/admin');
        cy.get('[data-testid="admin-dashboard-active-users"]').should(
            'be.visible',
        );
    });

    it('goes from user detail to workspace detail and back via the member link', () => {
        // Users index -> user detail.
        cy.contains('table tbody tr', email)
            .find('[data-testid="admin-user-view"]')
            .click();

        cy.url().should('match', /\/admin\/users\/[a-f0-9-]+/);
        cy.contains(email).should('be.visible');

        // The user uuid comes from the URL at runtime, so the dependent
        // commands must be nested inside the .then() callback (Cypress
        // evaluates selectors at enqueue time).
        cy.url().then((url) => {
            userUuid = url.match(/\/admin\/users\/([a-f0-9-]+)/)[1];

            // User detail -> workspace detail.
            cy.get(
                `[data-testid="admin-user-workspace-${workspaceUuid}"]`,
            ).click();
            cy.url().should('include', `/admin/workspaces/${workspaceUuid}`);
            cy.contains('h1', workspaceName).should('be.visible');

            // Workspace detail -> member (user) link -> back on user detail.
            cy.get(`[data-testid="admin-member-${userUuid}"]`).click();
            cy.url().should('include', `/admin/users/${userUuid}`);
            cy.contains(email).should('be.visible');
        });
    });
});
