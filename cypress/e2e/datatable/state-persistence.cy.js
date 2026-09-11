describe('DataTable State Persistence', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('datatable-state-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Datatable State');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            // Create account
            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.get('#type').click();
            cy.contains('Corrente').click();
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');

            // Create a category for filtering
            cy.url().should('include', '/accounts');
            cy.get('[data-testid="sidebar-categories"]').click();
            cy.contains('Nova Categoria').click({ force: true });
            cy.get('#name').type('Alimentação');
            cy.contains('Criar Categoria').click({ force: true });
            cy.assertToast('success', 'criada');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('datatable-state-session');
        cy.visit(`/w/${workspaceUuid}`);
    });

    it('renders transactions page without crashing', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');
    });

    it('persists filters across navigation', () => {
        // Intercept early so the initial load request and the filtered
        // request can be distinguished; the filtered fetch is what saves
        // the state to the server-side session.
        cy.intercept('GET', '**/transactions/datatable**').as('datatable');

        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');

        // Wait for the initial table load before applying the filter
        cy.wait('@datatable');

        // Apply category filter (2nd select trigger: account, category, status)
        cy.get('[data-slot="select-trigger"]').eq(1).click();
        cy.contains('[role="option"]', 'Alimentação').click();

        // Wait for the filtered fetch to finish so the session is saved
        cy.wait('@datatable');

        // Wait for table to update
        cy.get('body').should('contain', 'Filtros ativos');

        // Navigate away to planning
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.url().should('include', '/planning');

        // Return to transactions
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');

        // Assert: filter badge still visible
        cy.contains('Filtros ativos').should('be.visible');
        cy.contains('Categoria: Alimentação').should('be.visible');
    });

    it('clears filters with "Limpar Filtros" button', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');

        // Apply category filter (2nd select trigger: account, category, status)
        cy.get('[data-slot="select-trigger"]').eq(1).click();
        cy.contains('[role="option"]', 'Alimentação').click();

        // Wait for badge to appear
        cy.contains('Categoria: Alimentação').should('be.visible');

        // Click "Limpar Filtros"
        cy.contains('button', 'Limpar Filtros').click({ force: true });

        // Assert: badges disappear
        cy.contains('Categoria: Alimentação').should('not.exist');
        cy.contains('Filtros ativos').should('not.exist');
    });

    it('removes individual filter via badge X button', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');

        // Apply category filter (2nd select trigger: account, category, status)
        cy.get('[data-slot="select-trigger"]').eq(1).click();
        cy.contains('[role="option"]', 'Alimentação').click();

        // Wait for badge
        cy.contains('Categoria: Alimentação').should('be.visible');

        // Click X on the badge
        cy.contains('Categoria: Alimentação')
            .closest('[data-slot="badge"], .badge, [class*="badge"]')
            .find('button')
            .click({ force: true });

        // Assert: badge disappears
        cy.contains('Categoria: Alimentação').should('not.exist');
    });

    it('keeps state isolated between tables', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');

        // Apply filter in transactions (2nd select trigger: account, category, status)
        cy.get('[data-slot="select-trigger"]').eq(1).click();
        cy.contains('[role="option"]', 'Alimentação').click();
        cy.contains('Categoria: Alimentação').should('be.visible');

        // Navigate to incomes
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Receitas').should('be.visible');

        // Assert: no active filters in incomes
        cy.contains('Filtros ativos').should('not.exist');
        cy.contains('Categoria: Alimentação').should('not.exist');
    });
});
