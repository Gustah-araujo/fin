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
            cy.get('#type').click();
            cy.contains('Corrente').click();
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

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();

        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

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

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();

        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        cy.get('[data-slot="switch"]').click();

        cy.contains('Criar Receita').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/incomes');
        cy.contains('Freelance').should('be.visible');
        cy.contains('Recorrente').should('be.visible');
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
