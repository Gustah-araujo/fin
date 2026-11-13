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
            cy.selectCombobox('#type', 'Corrente');
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
        cy.selectCombobox('#credit_card_id', 'Nubank Recorrente');

        // Fill description and value
        cy.get('#description').type('Streaming Mensal');
        cy.get('#value').type('59.90');

        // Select category
        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Select the "Recorrência" modality (replaces the old #is_recurring Switch)
        cy.get('#modality-recurrence').click();

        // Verify frequency fields appear
        cy.contains('Frequência').should('be.visible');

        // Submit
        cy.contains('Criar Despesa').click({ force: true });

        // Verify redirect happened (catches 422/500 before vague toast timeout)
        cy.url().should('include', '/transactions');
        cy.assertToast('success', 'criada');
        cy.contains('Streaming Mensal').should('be.visible');

        // Navigate to recurrences and verify it appears
        cy.get('[data-testid="sidebar-recurrences"]').click();
        cy.contains('Streaming Mensal').should('be.visible');
    });

    // ── Recurrence and installments are mutually exclusive ───────
    it('keeps recurrence and installment modalities exclusive', () => {
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
        cy.selectCombobox('#credit_card_id', 'Nubank Block Test');

        // Fill description and value
        cy.get('#description').type('Recorrência com Parcela');
        cy.get('#value').type('300');
        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Select the "Recorrência" modality — recurrence fields appear and
        // installment fields must NOT be rendered (no lock/disabled state
        // anymore: modalities are mutually exclusive in ModalitySelector)
        cy.get('#modality-recurrence').click();
        cy.contains('Frequência').should('be.visible');
        cy.get('#installments').should('not.exist');
        cy.get('#total_value').should('not.exist');

        // Submit — succeeds because recurrence cannot carry installments
        cy.contains('Criar Despesa').click({ force: true });

        // Verify redirect happened (catches 422/500 before vague toast timeout)
        cy.url().should('include', '/transactions');
        cy.assertToast('success', 'criada');
    });

    // ── Block card recurrence with paid bill collision ───────────
    it('shows error for card recurrence with paid bill collision', () => {
        // Create card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank PaidBill');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('1');
        cy.get('#due_day').type('10');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Already on the card Show page — capture UUID from current URL
        cy.contains('h1', 'Nubank PaidBill').should('be.visible');
        cy.url().should('match', /\/cards\/([a-f0-9-]+)$/);
        cy.url().then((url) => {
            const cardUuid = url.match(/\/cards\/([a-f0-9-]+)$/)[1];

            // Create an expense on this card (bill must have total > 0 to be payable)
            cy.contains('a, button', 'Nova despesa neste cartão').click();
            cy.get('#description').type('Despesa para Fatura');
            cy.get('#value').type('200');
            cy.selectCombobox('#category_id', 'Sem Categoria');
            cy.contains('Criar Despesa').click({ force: true });
            cy.assertToast('success', 'criada');

            // Close the current bill via API
            cy.get('meta[name="csrf-token"]').then((meta) => {
                cy.request({
                    method: 'POST',
                    url: `/w/${workspaceUuid}/bills/close-current`,
                    headers: {
                        'X-CSRF-TOKEN': meta.attr('content'),
                    },
                    body: {
                        credit_card_id: cardUuid,
                    },
                });
            });

            // Reload the card show page to see the closed bill
            cy.visit(`/w/${workspaceUuid}/cards/${cardUuid}`);

            // Pay the bill via UI
            cy.contains('Marcar fatura como paga').click();
            cy.contains('Confirmar Pagamento').should('be.visible');
            cy.selectCombobox(
                '[data-slot="select-trigger"]',
                'Conta Principal',
            );
            cy.contains('Confirmar Pagamento').click();
            cy.contains('Paga').should('be.visible');

            // Wait for the paid-branch to render (dialog is unmounted when bill.status === 'paid')
            cy.contains('Desfazer pagamento').should('be.visible');

            // Wait for Radix UI scroll-lock to clear after dialog close animation
            cy.get('body', { timeout: 10000 }).should(
                'not.have.attr',
                'data-scroll-locked',
            );

            // Navigate to transactions create page via sidebar
            cy.get('[data-testid="sidebar-transactions"]').click();
            cy.contains('Nova Despesa').click({ force: true });
            cy.url().should('include', '/transactions/create');

            // Select card payment method
            cy.contains('button', 'Cartão de crédito').click();

            // Select card
            cy.selectCombobox('#credit_card_id', 'Nubank PaidBill');

            // Fill description and value
            cy.get('#description').type('Recorrência Colisão');
            cy.get('#value').type('100');

            // Select category
            cy.selectCombobox('#category_id', 'Sem Categoria');

            // Select the "Recorrência" modality
            cy.get('#modality-recurrence').click();

            // Set date to current month (which has a paid bill)
            const today = new Date();
            const currentDate = today.toISOString().split('T')[0];
            cy.get('#date').clear().type(currentDate);

            // Submit
            cy.contains('Criar Despesa').click({ force: true });

            // Should show validation error about paid bill collision
            cy.contains('faturas já pagas').should('be.visible');
        });
    });
});
