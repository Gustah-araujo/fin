describe('Transaction Expense Groups', () => {
    let workspaceUuid;

    function createGroupViaUi(name, color) {
        cy.get('[data-testid="sidebar-expense-groups"]').click();
        cy.contains('Novo Grupo').click({ force: true });
        cy.url().should('include', '/expense-groups/create');

        cy.get('#name').type(name);
        cy.get('input[placeholder="#000000"]').clear().type(color);
        cy.contains('Criar Grupo').click({ force: true });
        cy.assertToast('success', 'criado com sucesso');
        cy.window().then((win) => {
            win.__toastBuffer = [];
        });
    }

    function createExpense(description, value, category, options = {}) {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');

        cy.get('#description').type(description);
        cy.get('#value').type(value);
        cy.selectCombobox('#account_id', 'Conta Principal');
        cy.selectCombobox('#category_id', category);

        if (options.expectHint) {
            cy.contains(`Herdado da categoria: ${category}`).should(
                'be.visible',
            );
        }
        if (options.group) {
            cy.selectCombobox('#expense_group_id', options.group);
        }

        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');
        cy.window().then((win) => {
            win.__toastBuffer = [];
        });

        cy.url().should('include', '/transactions');
    }

    function groupFilterTrigger() {
        // The 'Grupo' column is the first column, so its filter select lives
        // in the first cell of the filter row (second row of the thead).
        return cy
            .get('[data-slot="table-header"] [data-slot="table-row"]')
            .eq(1)
            .find('[data-slot="table-head"]')
            .first()
            .find('[data-slot="select-trigger"]');
    }

    function applyGroupFilter(optionText) {
        groupFilterTrigger().click();
        cy.contains('[role="option"]', optionText).click();
        cy.get('[data-radix-popper-content-wrapper]').should('not.exist');
    }

    before(() => {
        cy.loginViaSession('expense-group-transactions-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Expense Group Transactions');
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

            createGroupViaUi('Contas Básicas', '#EF4444');
            createGroupViaUi('Lazer', '#3B82F6');

            cy.get('[data-testid="sidebar-categories"]').click();
            cy.contains('Nova Categoria').click({ force: true });
            cy.get('#name').type('Mercado');
            cy.selectCombobox('#expense_group_id', 'Contas Básicas');
            cy.contains('Criar Categoria').click({ force: true });
            cy.assertToast('success', 'criada');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('expense-group-transactions-session');

        cy.visit(`/w/${workspaceUuid}/transactions`);
    });

    it('renders the transactions page with the Grupo column (smoke)', () => {
        cy.contains('Despesas').should('be.visible');
        cy.contains('th', 'Grupo').should('be.visible');
        cy.contains('Nova Despesa').should('be.visible');
    });

    it('creates an expense inheriting the group from the category', () => {
        createExpense('Despesa Herdada', '100', 'Mercado', {
            expectHint: true,
        });
    });

    it('creates an expense with an explicit group override', () => {
        createExpense('Despesa Sobrescrita', '50', 'Mercado', {
            group: 'Lazer',
        });
    });

    it('creates an expense without any group', () => {
        createExpense('Despesa Sem Grupo', '25', 'Sem Categoria');
    });

    it('shows the group dot per row with the effective group color', () => {
        cy.contains('Despesa Herdada')
            .closest('tr')
            .find('[data-testid="group-dot"]')
            .should('have.css', 'background-color', 'rgb(239, 68, 68)');

        cy.contains('Despesa Sobrescrita')
            .closest('tr')
            .find('[data-testid="group-dot"]')
            .should('have.css', 'background-color', 'rgb(59, 130, 246)');

        cy.contains('Despesa Sem Grupo')
            .closest('tr')
            .find('[data-testid="group-dot"]')
            .should('not.exist');
    });

    it('filters expenses by group and updates the totals', () => {
        applyGroupFilter('Contas Básicas');

        cy.contains('Despesa Herdada').should('be.visible');
        cy.contains('Despesa Sobrescrita').should('not.exist');
        cy.get('[data-testid="total-expenses"]').should('contain', '100,00');
    });

    it('filters expenses by Sem Grupo', () => {
        applyGroupFilter('Sem Grupo');

        cy.contains('Despesa Sem Grupo').should('be.visible');
        cy.get('[data-testid="total-expenses"]').should('contain', '25,00');
    });

    it('clears the group filter and shows all expenses', () => {
        // Selecting the empty 'Todos' option cannot reset the filter because
        // table state persists in the session. 'Limpar Filtros' clears the
        // persisted state server-side and reloads the page.
        cy.contains('Filtros ativos').should('be.visible');
        cy.contains('button', 'Limpar Filtros').click({ force: true });

        cy.contains('Despesa Herdada').should('be.visible');
        cy.contains('Despesa Sobrescrita').should('be.visible');
        cy.contains('Despesa Sem Grupo').should('be.visible');
        cy.get('[data-testid="total-expenses"]').should('contain', '175,00');
    });
});
