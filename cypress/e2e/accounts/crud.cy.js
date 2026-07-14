describe('Account CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('accounts-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Accounts');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('accounts-session');
    });

    it('shows accounts index page', () => {
        cy.visit(`/w/${workspaceUuid}/accounts`);
        cy.contains('Contas').should('be.visible');
    });

    it('creates an account', () => {
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
    });

    it('shows validation errors on create', () => {
        cy.visit(`/w/${workspaceUuid}/accounts/create`);
        cy.contains('Criar Conta').click();

        cy.contains('O nome da conta é obrigatório').should('be.visible');
        cy.contains('O saldo inicial é obrigatório').should('be.visible');
    });

    it('edits an account', () => {
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
            .closest('[data-slot="card"]')
            .contains('button', 'Excluir')
            .click({ force: true });

        cy.contains('Para Excluir').should('not.exist');
    });
});
