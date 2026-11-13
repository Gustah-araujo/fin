describe('Income CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('incomes-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Incomes');
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
        cy.loginViaSession('incomes-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('shows incomes index page', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Receitas').should('be.visible');
    });

    it('shows validation errors on create', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').should('be.visible');
        cy.contains('Criar Receita').click({ force: true });

        cy.contains('A descrição é obrigatória').should('be.visible');
        cy.contains('O valor é obrigatório').should('be.visible');
        cy.contains('A conta é obrigatória').should('be.visible');
        cy.contains('A categoria é obrigatória').should('be.visible');
    });

    it('creates an avulsa income', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Salário');
        cy.get('#value').type('2000');

        cy.selectCombobox('#account_id', 'Conta Principal');

        cy.selectCombobox('#category_id', 'Sem Categoria');

        cy.contains('Criar Receita').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/incomes');
        cy.contains('Salário').should('be.visible');
    });

    it('confirms and unconfirms receipt', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();

        cy.contains('Salário')
            .closest('tr')
            .contains('Confirmar')
            .click({ force: true });
        cy.assertToast('success', 'confirmada');

        cy.contains('Salário')
            .closest('tr')
            .contains('Recebida')
            .should('be.visible');

        cy.contains('Salário')
            .closest('tr')
            .contains('Desmarcar')
            .click({ force: true });
        cy.assertToast('success', 'desconfirmada');

        cy.contains('Salário')
            .closest('tr')
            .contains('Prevista')
            .should('be.visible');
    });

    it('creates a recurring income and sees the first instance', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Freelance');
        cy.get('#value').type('800');

        cy.selectCombobox('#account_id', 'Conta Principal');

        cy.selectCombobox('#category_id', 'Sem Categoria');

        cy.get('[data-slot="switch"]').click();

        cy.contains('Criar Receita').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/incomes');
        cy.contains('Freelance').should('be.visible');
        cy.contains('Recorrente').should('be.visible');
    });

    it('has no parcelamento option in the income form', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').should('be.visible');

        // Income form keeps the Avulsa/Recorrente Switch — no expense
        // ModalitySelector with Parcelamento
        cy.contains('Parcelamento').should('not.exist');
        cy.get('#modality-installment').should('not.exist');
    });

    it('creates a recurring income with a data final', () => {
        // Recurrence runs until one year from today
        const endDate = new Date();
        endDate.setFullYear(endDate.getFullYear() + 1);
        const untilDate = endDate.toISOString().split('T')[0];

        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Aluguel Recebido');
        cy.get('#value').type('1200');

        cy.selectCombobox('#account_id', 'Conta Principal');

        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Recorrente via the (unchanged) Switch
        cy.get('#is_recurring').click();
        cy.contains('Frequência').should('be.visible');

        // Define a data final
        cy.get('#has_until_date').click();
        cy.get('#until_date').type(untilDate);

        cy.contains('Criar Receita').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/incomes');
        cy.contains('Aluguel Recebido').should('be.visible');

        // Recurrence appears in the recurrences list
        cy.get('[data-testid="sidebar-recurrences"]').click();
        cy.contains('Aluguel Recebido').should('be.visible');
    });

    it('edits an income', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();

        cy.contains('Salário')
            .closest('tr')
            .contains('Editar')
            .click({ force: true });

        cy.get('#description').clear().type('Salário Mensal');
        cy.contains('Salvar').click({ force: true });
        cy.assertToast('success', 'atualizada');

        cy.url().should('include', '/incomes');
        cy.contains('Salário Mensal').should('be.visible');
    });

    it('deletes an income', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();

        cy.contains('Salário Mensal')
            .closest('tr')
            .contains('button', 'Excluir')
            .click({ force: true });

        cy.contains('Salário Mensal').should('not.exist');
    });
});
