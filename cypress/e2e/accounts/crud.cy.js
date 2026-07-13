describe('Account CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.registerAndCreateWorkspace('Workspace Accounts').then((uuid) => {
            workspaceUuid = uuid;
        });
    });

    it('creates an account and sees it in the list', () => {
        cy.visit(`/w/${workspaceUuid}/accounts`);

        cy.contains('Nova Conta').click();
        cy.url().should('include', '/accounts/create');

        cy.get('#name').type('Conta Teste');
        cy.get('#type').click();
        cy.contains('Corrente').click();
        cy.get('#initial_balance').type('1000');
        cy.contains('Criar Conta').click();

        cy.url().should('include', '/accounts');
        cy.contains('Conta Teste').should('be.visible');
        cy.contains('Corrente').should('be.visible');
    });

    it('shows validation errors on empty submission', () => {
        cy.visit(`/w/${workspaceUuid}/accounts/create`);

        cy.contains('Criar Conta').click();

        cy.contains('O nome da conta é obrigatório').should('be.visible');
        cy.contains('O saldo inicial é obrigatório').should('be.visible');
    });

    it('edits an existing account', () => {
        cy.visit(`/w/${workspaceUuid}/accounts/create`);
        cy.get('#name').type('Para Editar');
        cy.get('#type').click();
        cy.contains('Corrente').click();
        cy.get('#initial_balance').type('500');
        cy.contains('Criar Conta').click();

        cy.url().should('include', '/accounts');
        cy.contains('Para Editar').should('be.visible');
        cy.contains('Para Editar')
            .parents('[class*="Card"]')
            .contains('Editar')
            .click();

        cy.get('#name').clear().type('Conta Editada');
        cy.contains('Salvar').click();

        cy.url().should('include', '/accounts');
        cy.contains('Conta Editada').should('be.visible');
    });

    it('deletes an account', () => {
        cy.visit(`/w/${workspaceUuid}/accounts/create`);
        cy.get('#name').type('Para Excluir');
        cy.get('#type').click();
        cy.contains('Poupança').click();
        cy.get('#initial_balance').type('200');
        cy.contains('Criar Conta').click();

        cy.url().should('include', '/accounts');
        cy.contains('Para Excluir').should('be.visible');
        cy.contains('Para Excluir')
            .parents('[class*="Card"]')
            .contains('Excluir')
            .click();

        cy.contains('Para Excluir').should('not.exist');
    });
});
