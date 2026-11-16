describe('Dashboard Expense Groups', () => {
    let workspaceUuid;

    function createExpense(description, value, category) {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');

        cy.get('#description').type(description);
        cy.get('#value').type(value);
        cy.selectCombobox('#account_id', 'Conta Principal');
        cy.selectCombobox('#category_id', category);

        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');
        cy.window().then((win) => {
            win.__toastBuffer = [];
        });

        cy.url().should('include', '/transactions');
    }

    before(() => {
        cy.loginViaSession('dashboard-expense-groups-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Dashboard Groups');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.selectCombobox('#type', 'Corrente');
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');

            cy.get('[data-testid="sidebar-expense-groups"]').click();
            cy.contains('Novo Grupo').click({ force: true });
            cy.get('#name').type('Contas Básicas');
            cy.contains('Criar Grupo').click({ force: true });
            cy.assertToast('success', 'criado com sucesso');

            cy.get('[data-testid="sidebar-categories"]').click();
            cy.contains('Nova Categoria').click({ force: true });
            cy.get('#name').type('Casa');
            cy.selectCombobox('#expense_group_id', 'Contas Básicas');
            cy.contains('Criar Categoria').click({ force: true });
            cy.assertToast('success', 'criada');

            createExpense('Aluguel', '100', 'Casa');
            createExpense('Diversos', '50', 'Sem Categoria');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('dashboard-expense-groups-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('renders the dashboard with group and category cards (smoke)', () => {
        cy.contains('Gastos por Grupo de Despesa').should('be.visible');
        cy.contains('Gastos por Categoria').should('be.visible');
    });

    it('shows the stacked bar with group segments', () => {
        cy.get('[data-testid="group-segment"]').should('have.length', 2);
        cy.get('[data-testid="group-segment"]')
            .first()
            .should('be.visible');
    });

    it('shows the group legend with values and percentages', () => {
        cy.contains('li', 'Contas Básicas')
            .should('be.visible')
            .contains('R$ 100,00')
            .should('be.visible');
        cy.contains('li', 'Contas Básicas').contains('66,7%');

        cy.contains('li', 'Sem Grupo')
            .should('be.visible')
            .contains('R$ 50,00')
            .should('be.visible');
        cy.contains('li', 'Sem Grupo').contains('33,3%');
    });

    it('shows the category ranking', () => {
        cy.contains('Casa').should('be.visible');
        cy.contains('R$ 100,00').should('be.visible');
        cy.contains('Sem Categoria').should('be.visible');
    });

    it('shows the empty state for a workspace without groups', () => {
        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Dashboard No Groups');
        cy.get('button[type="submit"]').click();
        cy.url().should('match', /\/w\/([a-f0-9-]+)/);

        cy.contains('Nenhum grupo de despesa cadastrado').should('be.visible');
        cy.contains('a', 'Criar primeiro grupo').should('be.visible');
    });
});
