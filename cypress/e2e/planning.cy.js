describe('Planning (Planejamento Futuro)', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('planning-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Planning');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            // Create an account for transaction setup
            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.get('#type').click();
            cy.contains('Corrente').click();
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
            cy.url().should('include', '/accounts');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('planning-session');
        cy.visit(`/w/${workspaceUuid}`);
    });

    it('shows planning page with cards and empty state', () => {
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.contains('Planejamento Futuro').should('be.visible');
        cy.contains('Projeção de gastos e rendas').should('be.visible');

        // Totals cards visible
        cy.contains('Gastos Comprometidos').should('be.visible');
        cy.contains('Rendas Programadas').should('be.visible');
        cy.contains('Saldo Projetado').should('be.visible');

        // Empty state when no transactions
        cy.contains('Nenhuma transação encontrada no período selecionado').should(
            'be.visible',
        );
    });

    it('shows projection table with recurring income', () => {
        // Create a recurring income via the Incomes page
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Salário Mensal');
        cy.get('#value').type('5000');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        // Make it recurring
        cy.get('[data-slot="switch"]').click();
        cy.contains('Criar Receita').click({ force: true });
        cy.url().should('include', '/incomes');

        // Create a one-time expense (appears in current month)
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click();
        cy.get('#description').type('Aluguel');
        cy.get('#value').type('1500');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.url().should('include', '/transactions');

        // Navigate to Planning
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.contains('Planejamento Futuro').should('be.visible');

        // Table should exist and have rows (recurring income projects + current month expense)
        cy.get('table').should('exist');
        cy.get('table tbody tr').should('have.length.greaterThan', 0);
    });

    it('changes period preset and table updates', () => {
        // Create a recurring income first
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Freelance');
        cy.get('#value').type('2000');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.get('[data-slot="switch"]').click();
        cy.contains('Criar Receita').click({ force: true });
        cy.url().should('include', '/incomes');

        // Go to planning
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.contains('Planejamento Futuro').should('be.visible');

        // Select "Próximos 3 meses"
        cy.get('[data-slot="select-trigger"]').click();
        cy.contains('[role="option"]', 'Próximos 3 meses').click();

        // Table should render with rows
        cy.get('table').should('exist');
        cy.get('table tbody tr').should('have.length', 3);

        // Select "Próximo mês"
        cy.get('[data-slot="select-trigger"]').click();
        cy.contains('[role="option"]', 'Próximo mês').click();
        cy.get('table tbody tr').should('have.length', 1);
    });

    it('opens month detail modal on row click', () => {
        // Create recurring income
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Consultoria');
        cy.get('#value').type('3000');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.get('[data-slot="switch"]').click();
        cy.contains('Criar Receita').click({ force: true });
        cy.url().should('include', '/incomes');

        // Go to planning
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.contains('Planejamento Futuro').should('be.visible');

        // Wait for table to have rows
        cy.get('table tbody tr').should('have.length.greaterThan', 0);

        // Click first month row
        cy.get('table tbody tr').first().click();

        // Modal should open with detail title
        cy.get('[role="dialog"]').should('be.visible');
        cy.contains('Detalhamento').should('be.visible');

        // Close modal
        cy.contains('Fechar').click();
        cy.get('[role="dialog"]').should('not.exist');
    });

    it('selects custom period with date inputs', () => {
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.contains('Planejamento Futuro').should('be.visible');

        // Select "Personalizado"
        cy.get('[data-slot="select-trigger"]').click();
        cy.contains('[role="option"]', 'Personalizado').click();

        // Date inputs should appear
        cy.get('input[type="date"]').should('have.length', 2);

        // Set custom dates to current month
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        const formatDate = (d) => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        };

        cy.get('input[type="date"]').first().type(formatDate(start), {
            force: true,
        });
        cy.get('input[type="date"]').last().type(formatDate(end), {
            force: true,
        });

        // Table or empty state should be visible
        cy.get('table, .text-muted-foreground').should('exist');
    });

    it('filters to expenses only hides Rendas column', () => {
        // Create recurring income
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Renda Teste');
        cy.get('#value').type('4000');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.get('[data-slot="switch"]').click();
        cy.contains('Criar Receita').click({ force: true });
        cy.url().should('include', '/incomes');

        // Create one-time expense in current month
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click();
        cy.get('#description').type('Gasto Teste');
        cy.get('#value').type('800');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.url().should('include', '/transactions');

        // Go to planning
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.contains('Planejamento Futuro').should('be.visible');

        // Table should have both columns
        cy.get('table th').contains('Gastos').should('be.visible');
        cy.get('table th').contains('Rendas').should('be.visible');

        // Click "Apenas Gastos"
        cy.contains('button', 'Apenas Gastos').click();

        // Rendas column should be hidden
        cy.get('table th').contains('Rendas').should('not.exist');
        cy.get('table th').contains('Gastos').should('be.visible');

        // Rendas Programadas card should also be hidden
        cy.contains('Rendas Programadas').should('not.exist');
        cy.contains('Gastos Comprometidos').should('be.visible');
    });

    it('filters to incomes only hides Gastos column', () => {
        // Create recurring income
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Renda Filter');
        cy.get('#value').type('3500');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.get('[data-slot="switch"]').click();
        cy.contains('Criar Receita').click({ force: true });
        cy.url().should('include', '/incomes');

        // Go to planning
        cy.get('[data-testid="sidebar-planning"]').click();
        cy.contains('Planejamento Futuro').should('be.visible');

        // Click "Apenas Rendas"
        cy.contains('button', 'Apenas Rendas').click();

        // Gastos column should be hidden
        cy.get('table th').contains('Gastos').should('not.exist');
        cy.get('table th').contains('Rendas').should('be.visible');

        // Gastos Comprometidos card should also be hidden
        cy.contains('Gastos Comprometidos').should('not.exist');
        cy.contains('Rendas Programadas').should('be.visible');
    });
});
