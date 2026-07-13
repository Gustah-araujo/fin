describe('Category CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.registerAndCreateWorkspace('Workspace Categories').then((uuid) => {
            workspaceUuid = uuid;
        });
    });

    it('full CRUD lifecycle', () => {
        // Default category + empty state
        cy.visit(`/w/${workspaceUuid}/categories`);
        cy.contains('Categorias').should('be.visible');
        cy.contains('Sem Categoria').should('be.visible');
        cy.contains('Padrão').should('be.visible');

        // Create
        cy.contains('Nova Categoria').click({ force: true });
        cy.url().should('include', '/categories/create');

        cy.get('#name').type('Alimentação');
        cy.get('#type').click();
        cy.contains('Despesa').click();
        cy.contains('Criar Categoria').click({ force: true });

        cy.url().should('include', '/categories');
        cy.contains('Alimentação').should('be.visible');

        // Validation errors
        cy.contains('Nova Categoria').click({ force: true });
        cy.contains('Criar Categoria').click({ force: true });
        cy.contains('O nome da categoria é obrigatório').should('be.visible');

        // Edit
        cy.visit(`/w/${workspaceUuid}/categories`);
        cy.contains('Alimentação')
            .closest('[data-slot="card"]')
            .contains('Editar')
            .click({ force: true });

        cy.url().should('include', '/categories/');
        cy.get('#name').clear().type('Alimentação Editada');
        cy.contains('Salvar').click({ force: true });

        cy.url().should('include', '/categories');
        cy.contains('Alimentação Editada').should('be.visible');

        // Delete
        cy.visit(`/w/${workspaceUuid}/categories/create`);
        cy.get('#name').type('Para Excluir');
        cy.get('#type').click();
        cy.contains('Despesa').click();
        cy.contains('Criar Categoria').click({ force: true });

        cy.url().should('include', '/categories');
        cy.contains('Para Excluir').should('be.visible');
        cy.contains('Para Excluir')
            .closest('[data-slot="card"]')
            .contains('button', 'Excluir')
            .click({ force: true });

        cy.contains('Para Excluir').should('not.exist');
    });
});
