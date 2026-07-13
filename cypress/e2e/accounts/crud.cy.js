describe('Account CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.registerAndCreateWorkspace('Workspace Accounts').then((uuid) => {
            workspaceUuid = uuid;
        });
    });

    it('full CRUD lifecycle', () => {
        // Create
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

        // Validation errors
        cy.contains('Nova Conta').click();
        cy.contains('Criar Conta').click();
        cy.contains('O nome da conta é obrigatório').should('be.visible');
        cy.contains('O saldo inicial é obrigatório').should('be.visible');

        // Edit
        cy.visit(`/w/${workspaceUuid}/accounts`);
        cy.contains('Conta Teste')
            .closest('[data-slot="card"]')
            .contains('Editar')
            .click();

        cy.url().should('include', '/accounts/');
        cy.get('#name').clear().type('Conta Editada');
        cy.contains('Salvar').click();

        cy.url().should('include', '/accounts');
        cy.contains('Conta Editada').should('be.visible');

        // Delete
        cy.visit(`/w/${workspaceUuid}/accounts/create`);
        cy.get('#name').type('Para Excluir');
        cy.get('#type').click();
        cy.contains('Poupança').click();
        cy.get('#initial_balance').type('200');
        cy.contains('Criar Conta').click();

        cy.url().should('include', '/accounts');
        cy.contains('Para Excluir').should('be.visible');
        cy.contains('Para Excluir')
            .closest('[data-slot="card"]')
            .contains('button', 'Excluir')
            .click({ force: true });

        cy.contains('Para Excluir').should('not.exist');
    });
});
