describe('Transaction Totals', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('totals-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Totals');
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

            cy.url().should('include', '/accounts');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('totals-session');

        cy.visit(`/w/${workspaceUuid}/transactions`);
    });

    function createExpense(description, value) {
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');
        cy.get('#description').type(description);
        cy.get('#value').type(value);

        cy.selectCombobox('#account_id', 'Conta Principal');

        cy.selectCombobox('#category_id', 'Sem Categoria');

        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');
        cy.window().then((win) => {
            win.__toastBuffer = [];
        });

        cy.url().should('include', '/transactions');
    }

    it('renders totals cards', () => {
        cy.contains('Total').should('be.visible');
        cy.contains('Total Pago').should('be.visible');
        cy.contains('Total Pendente').should('be.visible');
    });

    it('shows correct totals', () => {
        createExpense('Totalizador 100', '100');
        createExpense('Totalizador 50', '50');
        createExpense('Totalizador 25', '25');

        cy.contains('Totalizador 50')
            .closest('tr')
            .contains('Pagar')
            .click({ force: true });
        cy.assertToast('success', 'paga');

        cy.get('[data-testid="total-expenses"]').should('contain', '175,00');
        cy.get('[data-testid="total-paid"]').should('contain', '50,00');
        cy.get('[data-testid="total-pending"]').should('contain', '125,00');
    });

    it('scopes totals to the selected month', () => {
        // Isolate in a fresh workspace so totals start at zero and are not
        // affected by transactions created in the previous test.
        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Totals Scoped');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            const scopedWorkspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.selectCombobox('#type', 'Corrente');
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');

            cy.visit(`/w/${scopedWorkspaceUuid}/transactions`);

            createExpense('Totalizador Escopo 100', '100');

            cy.get('[data-testid="total-expenses"]').should('contain', '100,00');

            cy.get('[data-testid="month-prev"]').click();

            cy.get('[data-testid="total-expenses"]').should('contain', '0,00');
        });
    });
});
