describe('Recurring Expense on Credit Card', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('recurring-card-expense-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Recurring Card Expense');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            // Create a bank account (required for recurring expenses)
            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.selectCombobox('#type', 'Corrente');
            cy.get('#initial_balance').type('10000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');

            // Create a credit card
            cy.get('[data-testid="sidebar-cards"]').click();
            cy.contains('Novo Cartão').click();
            cy.get('#name').type('Nubank Recorrente');
            cy.get('#credit_limit').type('8000');
            cy.get('#closing_day').type('15');
            cy.get('#due_day').type('20');
            cy.contains('Criar Cartão').click();
            cy.assertToast('success', 'criado');

            // Create a tag with an explicit hex color (the badge style bug
            // crashed only when a transaction row carried a colored tag)
            cy.get('[data-testid="sidebar-tags"]').click();
            cy.contains('Nova Tag').click();
            cy.get('#name').type('Assinatura');
            cy.get('input[placeholder="#000000"]').clear().type('#EF4444');
            cy.get('input[placeholder="#000000"]').blur();
            cy.contains('Criar Tag').click();
            cy.assertToast('success', 'criada');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('recurring-card-expense-session');
        cy.visit(`/w/${workspaceUuid}`);
    });

    // ── Smoke test ──────────────────────────────────────────────
    it('renders the transactions listing without crashing', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();

        cy.contains('h1', 'Despesas').should('be.visible');
        cy.contains('Nova Despesa').should('be.visible');
        cy.contains('Total').should('be.visible');
        cy.contains('Total Pago').should('be.visible');
        cy.contains('Total Pendente').should('be.visible');
    });

    // ── Full journey ────────────────────────────────────────────
    it('creates a recurring expense on a credit card and the listing renders without errors', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');

        // Fill description and value
        cy.get('#description').type('Streaming Cartão Recorrente');
        cy.get('#value').type('49.90');

        // Payment method: credit card
        cy.contains('button', 'Cartão de crédito').click();

        // Select the card
        cy.selectCombobox('#credit_card_id', 'Nubank Recorrente');

        // Category
        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Modality: recurrence (replaces the old #is_recurring Switch).
        // Default frequency is monthly with day 1 — leave as is.
        cy.get('#modality-recurrence').click();
        cy.contains('Frequência').should('be.visible');

        // Select the tag chip
        cy.contains('Assinatura').click();

        // Submit
        cy.contains('Criar Despesa').click({ force: true });

        // Redirect to the transactions listing
        cy.url().should('include', '/transactions');
        cy.url().should('not.include', '/create');

        // Sonner success toast (flash → toast.success in use-flash-toasts)
        cy.assertToast('success', 'Recorrência criada');

        // The listing must render (this crashed before the tag badge fix)
        cy.contains('h1', 'Despesas').should('be.visible');

        // New expense visible in the table
        cy.contains('Streaming Cartão Recorrente').should('be.visible');

        // The row carries the tag badge, card name and open-bill status
        cy.contains('Streaming Cartão Recorrente')
            .closest('tr')
            .within(() => {
                cy.contains('Assinatura').should('be.visible');
                cy.contains('Nubank Recorrente').should('be.visible');
                cy.contains('Na fatura (Aberta)').should('be.visible');
            });
    });
});
