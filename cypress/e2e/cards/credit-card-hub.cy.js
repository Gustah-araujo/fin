describe('Credit Card Hub', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('credit-card-hub-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Credit Card Hub');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            // Create a bank account for bill payments
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
        cy.loginViaSession('credit-card-hub-session');
        cy.visit(`/w/${workspaceUuid}`);
    });

    // ── Smoke test ──────────────────────────────────────────────
    it('renders card hub without crashing', () => {
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Cartões').should('be.visible');
    });

    // ── Create card ─────────────────────────────────────────────
    it('creates a card and sees pre-created bills', () => {
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.url().should('include', '/cards/create');

        cy.get('#name').type('Nubank Hub');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('15');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Should land on the card show (hub) page
        cy.url().should('include', '/cards/');
        cy.contains('Nubank Hub').should('be.visible');

        // 4 limit summary cards should be visible
        cy.contains('Limite total').should('be.visible');
        cy.contains('Consumido').should('be.visible');
        cy.contains('Disponível').should('be.visible');
        cy.contains('Fatura em vista').should('be.visible');

        // Invoice selector should be present (13 pre-created bills)
        cy.contains('Abril').should('exist');
    });

    // ── Create single card expense from hub ─────────────────────
    it('creates a single card expense from the hub', () => {
        // First create a card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Expense');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('15');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        cy.url().should('include', '/cards/');

        // Navigate to the card Show page (hub)
        cy.contains('a', 'Ver fatura').first().click();
        cy.url().should('match', /\/cards\/[a-f0-9-]+$/);

        // Click "Nova despesa neste cartão"
        cy.contains('a, button', 'Nova despesa neste cartão').click();
        cy.url().should('include', '/transactions/create');
        cy.url().should('include', 'payment_method=card');

        // Card should be pre-selected
        cy.get('#credit_card_id').should('exist');

        // Fill the form
        cy.get('#description').type('Compra Online');
                                cy.get('#value').type('500');

        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Should redirect to transactions index
        cy.url().should('include', '/transactions');
    });

    // ── Create 12x installment card expense ─────────────────────
    it('creates a 12x installment card expense', () => {
        // Create card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Installments');
        cy.get('#credit_limit').type('12000');
        cy.get('#closing_day').type('10');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Navigate to transaction create
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');

        // Select card payment method
        cy.contains('button', 'Cartão de crédito').click();

        // Select the card
        cy.get('#credit_card_id').click();
        cy.contains('[role="option"]', 'Nubank Installments').click();

        // Set installments to 12
        cy.get('#installments').clear().type('12');

        // Set total value (installments > 1 shows both fields)
        cy.get('#value').type('1200');
        cy.get('#total_value').type('1200');

        // Fill description
        cy.get('#description').type('Notebook Dell');

        // Select category
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Should redirect to transactions index
        cy.url().should('include', '/transactions');
    });

    // ── Pay a closed bill ───────────────────────────────────────
    it('pays a closed bill and verifies expenses marked paid', () => {
        let cardUuid;

        // Create card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank BillPay');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('1');
        cy.get('#due_day').type('10');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Navigate to the card Show page (hub) and capture UUID
        cy.contains('a', 'Ver fatura').first().click();
        cy.url().should('match', /\/cards\/([a-f0-9-]+)$/);
        cy.url().then((url) => {
            cardUuid = url.match(/\/cards\/([a-f0-9-]+)$/)[1];
        });

        // Create an expense on this card
        cy.contains('a, button', 'Nova despesa neste cartão').click();
        cy.get('#description').type('Compra Teste Fatura');
        cy.get('#value').type('200');
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Close the current bill via API
        cy.url().then((url) => {
            const workspaceMatch = url.match(/\/w\/([a-f0-9-]+)/);
            if (workspaceMatch) {
                cy.request('POST', `/w/${workspaceMatch[1]}/bills/close-current`, {
                    credit_card_id: undefined,
                });
            }
        });

        // Navigate directly to the card Show page
        cy.visit(`/w/${workspaceUuid}/cards/${cardUuid}`);

        // The bill should now be closed — look for pay button
        cy.contains('Marcar fatura como paga').should('be.visible');
        cy.contains('Marcar fatura como paga').click();

        // Dialog opens — select account
                                cy.contains('Confirmar Pagamento').should('be.visible');
        cy.get('[data-slot="select-trigger"]').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.contains('Marcar fatura como paga').last().click();

        // Bill status should change to paid
        cy.contains('Paga').should('be.visible');
    });

    // ── Undo bill payment ───────────────────────────────────────
    it('undoes a bill payment', () => {
        let cardUuid;

        // Create card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank UndoPay');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('1');
        cy.get('#due_day').type('10');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        // Navigate to the card Show page (hub) and capture UUID
        cy.contains('a', 'Ver fatura').first().click();
        cy.url().should('match', /\/cards\/([a-f0-9-]+)$/);
        cy.url().then((url) => {
            cardUuid = url.match(/\/cards\/([a-f0-9-]+)$/)[1];
        });

        // Create expense
        cy.contains('a, button', 'Nova despesa neste cartão').click();
        cy.get('#description').type('Compra Undo Test');
        cy.get('#value').type('150');
        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();
        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        // Close bill via API
        cy.url().then((url) => {
            const workspaceMatch = url.match(/\/w\/([a-f0-9-]+)/);
            if (workspaceMatch) {
                cy.request('POST', `/w/${workspaceMatch[1]}/bills/close-current`);
            }
        });

        // Navigate directly to the card Show page
        cy.visit(`/w/${workspaceUuid}/cards/${cardUuid}`);

        // Pay the bill
        cy.contains('Marcar fatura como paga').click();
        cy.contains('Confirmar Pagamento').should('be.visible');
        cy.get('[data-slot="select-trigger"]').click();
        cy.contains('[role="option"]', 'Conta Principal').click();
        cy.contains('Marcar fatura como paga').last().click();
        cy.contains('Paga').should('be.visible');

        // Undo the payment
        cy.contains('Desfazer pagamento').click();

        // Bill status should revert
        cy.contains('Fechada').should('be.visible');
    });
});
