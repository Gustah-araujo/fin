describe('Admin Backoffice', () => {
    let workspaceUuid;

    before(() => {
        cy.createAdminSession();

        // Give the admin a workspace so the app shell (AppHeader) renders.
        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Admin Backoffice');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.createAdminSession();
    });

    // Smoke test: /admin renders without crashing.
    it('renders the admin dashboard without crashing', () => {
        cy.get('[data-testid="admin-dashboard-active-users"]').should(
            'be.visible',
        );
        cy.get('[data-testid="admin-sidebar-dashboard"]').should('be.visible');
    });

    it('lands on the dashboard after admin login with the active users widget', () => {
        cy.url().should('include', '/admin');
        cy.url().should('not.include', '/w/');

        cy.contains('Total de Usuários Ativos').should('be.visible');
        cy.get('[data-testid="admin-dashboard-active-users"]')
            .invoke('text')
            .should('match', /^\d+$/);
    });

    it('navigates through the sidebar: Dashboard, Usuários, Workspaces', () => {
        cy.get('[data-testid="admin-sidebar-users"]').click();
        cy.url().should('include', '/admin/users');
        cy.contains('h1', 'Usuários').should('be.visible');

        cy.get('[data-testid="admin-sidebar-workspaces"]').click();
        cy.url().should('include', '/admin/workspaces');
        cy.contains('h1', 'Workspaces').should('be.visible');

        cy.get('[data-testid="admin-sidebar-dashboard"]').click();
        cy.url().should('match', /\/admin\/?$/);
        cy.get('[data-testid="admin-dashboard-active-users"]').should(
            'be.visible',
        );
    });

    it('shows "Área Admin" in the app header and "Voltar para o App" returns to the app', () => {
        cy.visit(`/w/${workspaceUuid}`);

        // App -> Admin ("Área Admin" only rendered for admins).
        cy.get('[data-testid="admin-header-entry"]').should('be.visible');
        cy.get('[data-testid="admin-header-entry"]').click();
        cy.url().should('match', /\/admin\/?$/);

        // Admin -> App.
        cy.contains('a', 'Voltar para o App').click();
        cy.url().should('include', '/workspace/select');
    });
});
