describe('Expense Group CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('expense-groups-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Expense Groups');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('expense-groups-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('renders the expense groups page via sidebar (smoke)', () => {
        cy.get('[data-testid="sidebar-expense-groups"]').click();
        cy.contains('h1', 'Grupos de Despesa').should('be.visible');
        cy.contains('Novo Grupo').should('be.visible');
    });

    it('creates a group keeping the default color', () => {
        cy.get('[data-testid="sidebar-expense-groups"]').click();
        cy.contains('Novo Grupo').click({ force: true });
        cy.url().should('include', '/expense-groups/create');

        cy.get('#name').type('Contas Básicas');
        cy.contains('Criar Grupo').click({ force: true });
        cy.assertToast('success', 'criado com sucesso');

        cy.url().should('include', '/expense-groups');
        cy.contains('Contas Básicas')
            .closest('[data-slot="card"]')
            .find('div.rounded-full')
            .should('be.visible');
    });

    it('edits a group', () => {
        cy.get('[data-testid="sidebar-expense-groups"]').click();
        cy.contains('Contas Básicas')
            .closest('[data-slot="card"]')
            .contains('Editar')
            .click({ force: true });

        cy.url().should('include', '/expense-groups/');
        cy.get('#name').clear().type('Essenciais');
        cy.contains('Salvar').click({ force: true });
        cy.assertToast('success', 'atualizado');

        cy.url().should('include', '/expense-groups');
        cy.contains('Essenciais').should('be.visible');
    });

    it('deletes a group', () => {
        cy.get('[data-testid="sidebar-expense-groups"]').click();
        cy.contains('Essenciais')
            .closest('[data-slot="card"]')
            .contains('button', 'Excluir')
            .click({ force: true });
        cy.assertToast('success', 'excluído');

        cy.contains('Essenciais').should('not.exist');
        cy.contains('Nenhum grupo de despesa cadastrado').should('be.visible');
    });

    it('creates a category linked to a group', () => {
        cy.get('[data-testid="sidebar-expense-groups"]').click();
        cy.contains('Novo Grupo').click({ force: true });
        cy.get('#name').type('Lazer');
        cy.contains('Criar Grupo').click({ force: true });
        cy.assertToast('success', 'criado com sucesso');

        cy.get('[data-testid="sidebar-categories"]').click();
        cy.contains('Nova Categoria').click({ force: true });
        cy.url().should('include', '/categories/create');

        cy.get('#name').type('Cinema');
        cy.selectCombobox('#expense_group_id', 'Lazer');
        cy.contains('Criar Categoria').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/categories');
        cy.contains('Cinema')
            .closest('[data-slot="card"]')
            .contains('Lazer')
            .should('be.visible');
    });
});
