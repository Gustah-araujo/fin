describe('Card Recurrence', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('card-recurrence-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Card Recurrence');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            // Create a bank account (required for recurring expenses)
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
        cy.loginViaSession('card-recurrence-session');
        cy.visit(`/w/${workspaceUuid}`);
    });

    // ── Smoke test ──────────────────────────────────────────────
    it('renders transaction create page without crashing', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.contains('Dados da Despesa').should('be.visible');
    });

    // ── Create card recurrence ──────────────────────────────────
    it('creates a card recurrence and verifies it appears in recurrences list', () => {
        // Create a card first
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Recorrente');
        cy.get('#credit_limit').type('8000');
        cy.get('#closing_day').type('15');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Navigate to transactions.create
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');

        // Select card payment method
        cy.contains('button', 'Cartão de crédito').click();

        // Select the card
        cy.get('#credit_card_id').click();
        cy.contains('[role="option"]', 'Nubank Recorrente').click();

        // Fill description and value
        cy.get('#description').type('Streaming Mensal');
        cy.get('#value').type('59.90');

        // Select category
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        // Enable recurrence
        cy.get('#is_recurring').click();

        // Verify frequency fields appear
        cy.contains('Frequência').should('be.visible');

        // Submit
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Should redirect to transactions index
        cy.url().should('include', '/transactions');
        cy.contains('Streaming Mensal').should('be.visible');

        // Navigate to recurrences and verify it appears
        cy.get('[data-testid="sidebar-recurrences"]').click();
        cy.contains('Streaming Mensal').should('be.visible');
    });

    // ── Block card recurrence with installments ─────────────────
    it('blocks card recurrence with installments', () => {
        // Create card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Block Test');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('10');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Navigate to transactions.create
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');

        // Select card payment method
        cy.contains('button', 'Cartão de crédito').click();

        // Select card
        cy.get('#credit_card_id').click();
        cy.contains('[role="option"]', 'Nubank Block Test').click();

        // Enable recurrence first — this should lock installments to 1
        cy.get('#is_recurring').click();
        cy.contains('Frequência').should('be.visible');

        // Try to set installments to 3 (should be locked/disabled when recurring)
        cy.get('#installments').should('have.attr', 'disabled');

        // Fill required fields and submit
        cy.get('#description').type('Recorrência com Parcela');
        cy.get('#value').type('300');
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        // Submit — should succeed because installments is locked to 1
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');
    });

    // ── Block card recurrence with paid bill collision ───────────
    it('shows error for card recurrence with start_date in the past', () => {
        // Create card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Past Date');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('1');
        cy.get('#due_day').type('10');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Navigate to transactions.create
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });

        // Select card payment method
        cy.contains('button', 'Cartão de crédito').click();

        // Select card
        cy.get('#credit_card_id').click();
        cy.contains('[role="option"]', 'Nubank Past Date').click();

        // Fill description and value
        cy.get('#description').type('Data Passada');
        cy.get('#value').type('100');

        // Select category
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        // Enable recurrence
        cy.get('#is_recurring').click();

        // Set date to the past (should trigger validation error)
        const pastDate = '2024-01-15';
        cy.get('#date').clear().type(pastDate);

        // Submit
        cy.contains('Criar Despesa').click({ force: true });

        // Should show validation error about past date for recurrences
        cy.get('.text-destructive').should('exist');
    });
});
