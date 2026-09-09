describe('Recurring Expenses E2E', () => {
    let workspaceUuid;

    // Suppress ResizeObserver loop errors from Radix UI components
    beforeEach(() => {
        cy.on('uncaught:exception', (err) => {
            if (err.message.includes('ResizeObserver loop')) {
                return false;
            }
        });
    });

    before(() => {
        cy.loginViaSession('recurring-expenses-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Recurring Expenses');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.get('#type').click();
            cy.contains('Corrente').click();
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');

            cy.url().should('include', '/accounts');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('recurring-expenses-session');
        cy.visit(`/w/${workspaceUuid}`);
    });

    it('creates a recurring expense and sees it in the recurrence list', () => {
        // Navigate to expenses (Despesas) and create recurring expense
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click();

        cy.get('#description').type('Netflix');
        cy.get('#value').type('39.90');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();

        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        // Toggle recurrence ON
        cy.get('#is_recurring').click();

        // Verify frequency fields appear
        cy.contains('Frequência').should('be.visible');

        // Default frequency is monthly — leave as is

        // Submit
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Should redirect to transactions index with the new expense
        cy.url().should('include', '/transactions');
        cy.contains('Netflix').should('be.visible');

        // Navigate to recurrences and verify it appears there
        cy.get('[data-testid="sidebar-recurrences"]').click();
        cy.contains('Netflix').should('be.visible');

        // Verify the type badge is "Despesa" (red)
        cy.contains('Netflix')
            .closest('tr')
            .within(() => {
                cy.contains('Despesa').should('be.visible');
            });
    });

    it('filters recurrence list by expense type', () => {
        // First create a recurring income so we have both types in the list
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Freelance Recorrente');
        cy.get('#value').type('2000');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();

        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        cy.get('[data-slot="switch"]').click();
        cy.contains('Criar Receita').click({ force: true });
        cy.assertToast('success', 'criada');
        cy.url().should('include', '/incomes');

        // Navigate to recurrences page
        cy.get('[data-testid="sidebar-recurrences"]').click();
        cy.contains('Recorrências').should('be.visible');

        // Verify both income and expense recurrences are visible
        cy.contains('Netflix').should('be.visible');
        cy.contains('Freelance Recorrente').should('be.visible');

        // Filter by type "Despesa" using the DataTable filter row
        // The filter row is the second <tr> inside <thead>
        // Columns with Select filters: type(0), status(1), account(2), category(3)
        cy.get('table thead tr').eq(1).find('[data-slot="select-trigger"]').eq(0).click();

        // Select "Despesa" from the filter dropdown
        cy.contains('[role="option"]', 'Despesa').click();

        // Only expense recurrences should be visible
        cy.contains('Netflix').should('be.visible');
        cy.contains('Freelance Recorrente').should('not.exist');
    });

    it('shows recurring expense projection in planning', () => {
        // Create a recurring expense
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click();

        cy.get('#description').type('Academia Mensal');
        cy.get('#value').type('120');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();

        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        // Toggle recurrence ON
        cy.get('#is_recurring').click();

        // Submit
        cy.contains('Criar Despesa').click({ force: true });
        cy.url().should('include', '/transactions');

        // Navigate to planning
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.contains('Planejamento Futuro').should('be.visible');

        // Projection table should exist with rows
        cy.get('table').should('exist');
        cy.get('table tbody tr').should('have.length.greaterThan', 0);

        // The expenses column should show a non-zero amount
        cy.get('table tbody tr').first().within(() => {
            // Find the expenses cell (destructive colored, right-aligned)
            cy.get('td.text-destructive').invoke('text').should('not.contain', 'R$\u00a00,00');
        });

        // Verify the Gastos Comprometidos card shows a non-zero value
        cy.contains('Gastos Comprometidos').should('be.visible');
        cy.contains('Gastos Comprometidos')
            .parent()
            .parent()
            .within(() => {
                cy.get('p.text-2xl').invoke('text').should('not.contain', 'R$\u00a00,00');
            });
    });
});
