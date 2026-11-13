describe('Installment Expenses E2E', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('installments-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Installments');
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
        cy.loginViaSession('installments-session');
        cy.visit(`/w/${workspaceUuid}`);
    });

    // ── Smoke test ──────────────────────────────────────────────
    it('renders the Nova Despesa page without crashing', () => {
        cy.visit(`/w/${workspaceUuid}/transactions/create`);
        cy.contains('Nova Despesa').should('be.visible');
        cy.get('#description').should('be.visible');
    });

    // ── Scenario A: installments on a normal account ────────────
    it('creates a 3x installment expense on an account', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');

        cy.get('#description').type('Notebook Dell');

        // Select the "Parcelamento" modality — hides #value, shows InstallmentFields
        cy.get('#modality-installment').click();
        cy.contains('Parcelas').should('be.visible');
        cy.get('#value').should('not.exist');

        // 3x of 300.03 — the preview reacts to the inputs
        cy.get('#installments').type('{selectall}3');
        cy.get('#total_value').type('300.03');

        cy.contains('Serão lançadas 3 parcelas de R$ 100,01').should(
            'be.visible',
        );
        cy.contains('Exemplo: "Notebook Dell (1/3)"').should('be.visible');

        cy.selectCombobox('#account_id', 'Conta Principal');

        cy.selectCombobox('#category_id', 'Sem Categoria');

        cy.contains('Criar Despesa').click({ force: true });

        // Verify redirect happened (catches 422/500 before vague toast timeout)
        cy.url().should('include', '/transactions');
        cy.assertToast('success', 'criada');

        // First installment is dated today, so it shows on the current month listing
        cy.contains('Notebook Dell (1/3)').should('be.visible');
    });

    // ── Scenario B: installments on a credit card ───────────────
    it('creates a 2x installment card expense with a reactive preview', () => {
        // Create a card
        cy.get('[data-testid="sidebar-cards"]').click();
        cy.contains('Novo Cartão').click();
        cy.get('#name').type('Nubank Parcelas');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('15');
        cy.get('#due_day').type('20');
        cy.contains('Criar Cartão').click();
        cy.assertToast('success', 'criado');

        cy.url().then((url) => {
            const cardUuid = url.match(/\/cards\/([a-f0-9-]+)$/)[1];

            // Open the create form with the card pre-selected (same URL params
            // as the "Nova despesa neste cartão" hub link)
            cy.visit(
                `/w/${workspaceUuid}/transactions/create?payment_method=card&card_id=${cardUuid}`,
            );
            cy.get('#credit_card_id').should('exist');

            cy.get('#description').type('Monitor UltraWide');

            // Select the "Parcelamento" modality — the card form has no
            // #value input of its own; installments come from InstallmentFields
            cy.get('#modality-installment').click();
            cy.get('#value').should('not.exist');

            cy.get('#total_value').type('1000');
            cy.contains('Serão lançadas 2 parcelas de R$ 500,00').should(
                'be.visible',
            );

            // Preview updates dynamically when changing parcelas and total
            cy.get('#installments').type('{selectall}4');
            cy.contains('Serão lançadas 4 parcelas de R$ 250,00').should(
                'be.visible',
            );
            cy.contains('Exemplo: "Monitor UltraWide (1/4)"').should(
                'be.visible',
            );

            cy.get('#total_value').clear().type('801');
            cy.contains('Serão lançadas 4 parcelas de R$ 200,25').should(
                'be.visible',
            );

            // Final configuration: 2x of 1000
            cy.get('#installments').type('{selectall}2');
            cy.get('#total_value').clear().type('1000');
            cy.contains('Exemplo: "Monitor UltraWide (1/2)"').should(
                'be.visible',
            );

            cy.selectCombobox('#category_id', 'Sem Categoria');

            cy.contains('Criar Despesa').click({ force: true });

            // Verify redirect happened (catches 422/500 before vague toast timeout)
            cy.url().should('include', '/transactions');
            cy.assertToast('success', 'criada');

            // First installment is dated today, so it shows on the current month listing
            cy.contains('Monitor UltraWide (1/2)').should('be.visible');
        });
    });
});
