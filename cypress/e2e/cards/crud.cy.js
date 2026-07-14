describe('Credit Card CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('cards-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Cards');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('cards-session');
    });

    it('shows cards index page', () => {
        cy.visit(`/w/${workspaceUuid}/cards`);
        cy.contains('Cartões').should('be.visible');
    });

    it('creates a credit card', () => {
        cy.visit(`/w/${workspaceUuid}/cards`);
        cy.contains('Novo Cartão').click();
        cy.url().should('include', '/cards/create');

        cy.get('#name').type('Nubank Mastercard');
        cy.get('#credit_limit').type('10000');
        cy.get('#closing_day').type('1');
        cy.get('#due_day').type('10');
        cy.contains('Criar Cartão').click();

        cy.url().should('include', '/cards');
        cy.contains('Nubank Mastercard').should('be.visible');
    });

    it('shows validation errors on create', () => {
        cy.visit(`/w/${workspaceUuid}/cards/create`);
        cy.contains('Criar Cartão').click();

        cy.contains('O nome do cartão é obrigatório').should('be.visible');
        cy.contains('O limite do cartão é obrigatório').should('be.visible');
        cy.contains('O dia de fechamento é obrigatório').should('be.visible');
        cy.contains('O dia de vencimento é obrigatório').should('be.visible');
    });

    it('edits a credit card', () => {
        cy.visit(`/w/${workspaceUuid}/cards`);
        cy.contains('Nubank Mastercard')
            .closest('[data-slot="card"]')
            .contains('Editar')
            .click();

        cy.url().should('include', '/cards/');
        cy.get('#name').clear().type('Inter Visa');
        cy.contains('Salvar').click();

        cy.url().should('include', '/cards');
        cy.contains('Inter Visa').should('be.visible');
    });

    it('updates credit_limit and sees available_limit update', () => {
        cy.visit(`/w/${workspaceUuid}/cards`);
        cy.contains('Inter Visa')
            .closest('[data-slot="card"]')
            .contains('Editar')
            .click();

        cy.get('#credit_limit').clear().type('8000');
        cy.contains('Salvar').click();

        cy.url().should('include', '/cards');
        cy.contains('Inter Visa')
            .closest('[data-slot="card"]')
            .should('contain', '8.000,00');
    });

    it('deletes a credit card', () => {
        cy.visit(`/w/${workspaceUuid}/cards/create`);
        cy.get('#name').type('Para Excluir');
        cy.get('#credit_limit').type('5000');
        cy.get('#closing_day').type('5');
        cy.get('#due_day').type('15');
        cy.contains('Criar Cartão').click();

        cy.url().should('include', '/cards');
        cy.contains('Para Excluir').should('be.visible');

        cy.contains('Para Excluir')
            .closest('[data-slot="card"]')
            .contains('button', 'Excluir')
            .click({ force: true });

        cy.contains('Para Excluir').should('not.exist');
    });
});
