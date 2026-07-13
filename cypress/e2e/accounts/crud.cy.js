describe('Account CRUD', () => {
    it('creates an account and sees it in the list', () => {
        cy.login();
        cy.visit('/w/test-workspace/accounts');

        cy.contains('Nova Conta').click();
        cy.url().should('include', '/accounts/create');

        cy.get('#name').type('Conta Teste');
        cy.get('#type').click();
        cy.contains('Corrente').click();
        cy.get('#initial_balance').type('1000');
        cy.contains('Criar Conta').click();

        cy.url().should('include', '/accounts');
        cy.contains('Conta Teste').should('be.visible');
        cy.contains('R$').should('be.visible');
        cy.contains('Corrente').should('be.visible');
    });

    it('shows validation errors on empty submission', () => {
        cy.login();
        cy.visit('/w/test-workspace/accounts/create');

        cy.contains('Criar Conta').click();

        cy.contains('O nome da conta é obrigatório').should('be.visible');
        cy.contains('O saldo inicial é obrigatório').should('be.visible');
    });

    it('edits an existing account', () => {
        cy.login();

        cy.createAccount('Para Editar', 'checking', 500);

        cy.visit('/w/test-workspace/accounts');
        cy.contains('Para Editar').parents('[class*="Card"]').contains('Editar').click();

        cy.get('#name').clear().type('Conta Editada');
        cy.contains('Salvar').click();

        cy.url().should('include', '/accounts');
        cy.contains('Conta Editada').should('be.visible');
    });

    it('deletes an account', () => {
        cy.login();

        cy.createAccount('Para Excluir', 'savings', 200);

        cy.visit('/w/test-workspace/accounts');
        cy.contains('Para Excluir').should('be.visible');
        cy.contains('Para Excluir').parents('[class*="Card"]').contains('Excluir').click();

        cy.contains('Para Excluir').should('not.exist');
    });
});
