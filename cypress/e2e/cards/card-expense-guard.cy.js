describe('Card Expense Pay Guard', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('card-expense-guard-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Card Expense Guard');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            // Create a bank account
            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.get('#type').click();
            cy.contains('Corrente').click();
            cy.get('#initial_balance').type('10000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('card-expense-guard-session');
        cy.visit(`/w/${workspaceUuid}`);
    });

    // ── Smoke test ──────────────────────────────────────────────
    it('renders transactions index without crashing', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');
    });

    // ── Hides pay button for card expenses ──────────────────────
    it('hides pay button for card expenses in transaction list', () => {
        // Create a card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Guard');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('15');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Create a card expense
        cy.contains('Nova despesa neste cartão').click();
        cy.get('#description').type('Compra Guard Test');
        cy.get('#total_value').type('250');
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Navigate to transactions index
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');

        // The card expense row should show the card name in Cartão column
        cy.contains('Compra Guard Test').should('be.visible');

        // There should be NO "Pagar" button for this card expense
        cy.contains('Compra Guard Test')
            .closest('tr')
            .within(() => {
                cy.contains('Pagar').should('not.exist');
            });

        // But there should be an Editar button
        cy.contains('Compra Guard Test')
            .closest('tr')
            .within(() => {
                cy.contains('Editar').should('be.visible');
            });
    });

    // ── Shows bill-derived status for card expenses ─────────────
    it('shows bill-derived status for card expenses', () => {
        // Create card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Status');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('15');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Create expense
        cy.contains('Nova despesa neste cartão').click();
        cy.get('#description').type('Compra Status Test');
        cy.get('#total_value').type('300');
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Navigate to transactions index
        cy.get('[data-testid="sidebar-transactions"]').click();

        // The expense should show bill-derived status "Na fatura (Aberta)"
        cy.contains('Compra Status Test')
            .closest('tr')
            .within(() => {
                cy.contains('Na fatura (Aberta)').should('be.visible');
            });
    });

    // ── Filters transactions by card ────────────────────────────
    it('filters transactions by card', () => {
        // Create first card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Filter A');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('15');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Create expense on first card
        cy.contains('Nova despesa neste cartão').click();
        cy.get('#description').type('Compra Filtro A');
        cy.get('#total_value').type('100');
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Create second card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Filter B');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('10');
        cy.get('#due_day').type('15');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Create expense on second card
        cy.contains('Nova despesa neste cartão').click();
        cy.get('#description').type('Compra Filtro B');
        cy.get('#total_value').type('200');
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Navigate to transactions index
        cy.get('[data-testid="sidebar-transactions"]').click();

        // Both expenses should be visible
        cy.contains('Compra Filtro A').should('be.visible');
        cy.contains('Compra Filtro B').should('be.visible');

        // Filter by card using the Cartão column filter
        // The filter row uses select triggers in thead
        cy.get('table thead tr')
            .eq(1)
            .find('[data-slot="select-trigger"]')
            .eq(4) // Cartão is the 5th column (0-indexed: 4)
            .click();

        cy.contains('[role="option"]', 'Nubank Filter A').click();

        // Only first card's expense should be visible
        cy.contains('Compra Filtro A').should('be.visible');
        cy.contains('Compra Filtro B').should('not.exist');
    });

    // ── Filters transactions by bill (overrides month) ──────────
    it('filters transactions by bill', () => {
        // Create card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank BillFilter');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('1');
        cy.get('#due_day').type('10');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Create expense
        cy.contains('Nova despesa neste cartão').click();
        cy.get('#description').type('Compra Filtro Fatura');
        cy.get('#total_value').type('350');
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Navigate to transactions index
        cy.get('[data-testid="sidebar-transactions"]').click();

        // Expense should be visible
        cy.contains('Compra Filtro Fatura').should('be.visible');

        // Filter by bill using the Fatura column filter
        cy.get('table thead tr')
            .eq(1)
            .find('[data-slot="select-trigger"]')
            .eq(5) // Fatura is the 6th column (0-indexed: 5)
            .click();

        // Select the first bill option (Cartão — <period>)
        cy.contains('[role="option"]').first().click();

        // The expense from the current bill should still be visible
        cy.contains('Compra Filtro Fatura').should('be.visible');
    });
});
