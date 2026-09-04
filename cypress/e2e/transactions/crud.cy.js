describe('Transaction CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('transactions-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Transactions');
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

            cy.url().should('include', '/accounts');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('transactions-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('shows transactions index page', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');
    });

    it('shows validation errors on create', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.get('#description').should('be.visible');
        cy.contains('Criar Despesa').click({ force: true });

        cy.contains('A descrição é obrigatória').should('be.visible');
        cy.contains('O valor é obrigatório').should('be.visible');
        cy.contains('A conta é obrigatória').should('be.visible');
        cy.contains('A categoria é obrigatória').should('be.visible');
    });

    it('creates a transaction', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');
        cy.get('#description').should('be.visible');

        cy.get('#description').type('Compra Supermercado');
        cy.get('#value').type('156.90');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();

        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        cy.contains('Criar Despesa').click({ force: true });

        cy.url().should('include', '/transactions');
        cy.contains('Nova Despesa').should('be.visible');
    });

    it('edits a transaction', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Compra Supermercado').should('be.visible');
    });

    it('pays a transaction', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Compra Supermercado')
            .closest('tr')
            .contains('Pagar')
            .click({ force: true });

        cy.contains('Compra Supermercado')
            .closest('tr')
            .contains('Desmarcar')
            .should('be.visible');
    });

    it('unpays a transaction', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Compra Supermercado')
            .closest('tr')
            .contains('Desmarcar')
            .click({ force: true });

        cy.contains('Compra Supermercado')
            .closest('tr')
            .contains('Pagar')
            .should('be.visible');
    });

    it('deletes a transaction', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').should('be.visible');

        cy.contains('Compra Supermercado')
            .closest('tr')
            .contains('button', 'Excluir')
            .click({ force: true });

        cy.contains('Compra Supermercado').should('not.exist');
    });

    it('filters transactions by search', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.get('input[type="text"]').should('be.visible');
        cy.contains('Nova Despesa').should('be.visible');
    });

    it('filters transactions by status', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.get('[data-slot="select-trigger"]').should('have.length.at.least', 1);
        cy.contains('Nova Despesa').should('be.visible');
    });

    it('creates transaction with tags', () => {
        cy.get('[data-testid="sidebar-tags"]').click();
        cy.contains('Nova Tag').click({ force: true });
        cy.get('#name').type('urgente');
        cy.contains('Criar Tag').click({ force: true });
        cy.contains('urgente').should('be.visible');
    });
});
