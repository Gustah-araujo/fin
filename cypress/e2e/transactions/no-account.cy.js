describe('Transactions without account', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('transactions-no-account-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E No Account');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('transactions-no-account-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('renders despesas page without crashing', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');
    });

    it('creates a paid expense without an account', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');
        cy.get('#description').should('be.visible');

        cy.get('#description').type('Compra sem conta');
        cy.get('#value').type('150');

        cy.selectCombobox('#account_id', 'Sem conta');
        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Date defaults to today; mark as already paid
        cy.get('#is_paid').click();

        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/transactions');
        cy.contains('Compra sem conta')
            .should('be.visible')
            .closest('tr')
            .within(() => {
                // Status column: paid account expense renders "✓"
                cy.contains('✓').should('be.visible');
                // Account column: no linked account renders "—"
                cy.contains('—').should('be.visible');
            });
    });

    // ── Scenario: Recorrência without an account ─────────────────
    it('creates a recurring expense without an account', () => {
        // Recurrence runs until one year from today
        const endDate = new Date();
        endDate.setFullYear(endDate.getFullYear() + 1);
        const untilDate = endDate.toISOString().split('T')[0];

        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');
        cy.get('#description').should('be.visible');

        cy.get('#description').type('Assinatura Sem Conta');
        cy.get('#value').type('29.90');

        cy.selectCombobox('#account_id', 'Sem conta');
        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Select the "Recorrência" modality — shows the frequency fields
        cy.get('#modality-recurrence').click();
        cy.contains('Frequência').should('be.visible');

        // Default frequency is monthly — leave as is
        cy.get('#has_until_date').click();
        cy.get('#until_date').type(untilDate);

        cy.contains('Criar Despesa').click({ force: true });

        // The old bug rejected recurrences without account/card with a
        // validation error — it must NOT appear
        cy.contains(
            'Uma transação deve ter uma conta ou cartão selecionado.',
        ).should('not.exist');

        // Verify redirect happened (catches 422/500 before vague toast timeout)
        cy.url().should('include', '/transactions');
        cy.assertToast('success', 'criada');

        cy.contains('Assinatura Sem Conta')
            .should('be.visible')
            .closest('tr')
            .within(() => {
                // Account column: no linked account renders "—"
                cy.contains('—').should('be.visible');
            });

        // The recurrence appears in the recurrences list
        cy.get('[data-testid="sidebar-recurrences"]').click();
        cy.contains('Assinatura Sem Conta').should('be.visible');
    });

    // ── Scenario: Parcelamento without an account ────────────────
    it('creates an installment expense without an account', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');
        cy.get('#description').should('be.visible');

        cy.get('#description').type('Notebook Sem Conta');

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
        cy.contains('Exemplo: "Notebook Sem Conta (1/3)"').should('be.visible');

        cy.selectCombobox('#account_id', 'Sem conta');
        cy.selectCombobox('#category_id', 'Sem Categoria');

        cy.contains('Criar Despesa').click({ force: true });

        // The old bug rejected installments without account/card with a
        // validation error — it must NOT appear
        cy.contains(
            'Uma transação deve ter uma conta ou cartão selecionado.',
        ).should('not.exist');

        // Verify redirect happened (catches 422/500 before vague toast timeout)
        cy.url().should('include', '/transactions');
        cy.assertToast('success', 'criada');

        // First installment is dated today, so it shows on the current month listing
        cy.contains('Notebook Sem Conta (1/3)')
            .should('be.visible')
            .closest('tr')
            .within(() => {
                // Account column: no linked account renders "—"
                cy.contains('—').should('be.visible');
            });
    });

    // ── Scenario: recurring income without an account ────────────
    it('creates a recurring income without an account', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').should('be.visible');

        cy.get('#description').type('Freelance Sem Conta');
        cy.get('#value').type('800');

        cy.selectCombobox('#account_id', 'Sem conta');
        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Recorrente via the Switch
        cy.get('#is_recurring').click();
        cy.contains('Frequência').should('be.visible');

        cy.contains('Criar Receita').click({ force: true });

        // The old bug rejected recurrences without account/card with a
        // validation error — it must NOT appear
        cy.contains(
            'Uma transação deve ter uma conta ou cartão selecionado.',
        ).should('not.exist');

        // Verify redirect happened (catches 422/500 before vague toast timeout)
        cy.url().should('include', '/incomes');
        cy.assertToast('success', 'criada');

        cy.contains('Freelance Sem Conta').should('be.visible');

        // The recurrence appears in the recurrences list
        cy.get('[data-testid="sidebar-recurrences"]').click();
        cy.contains('Freelance Sem Conta').should('be.visible');
    });
});
