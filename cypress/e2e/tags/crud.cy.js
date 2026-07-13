describe('Tag CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.registerAndCreateWorkspace('Workspace Tags').then((uuid) => {
            workspaceUuid = uuid;
        });
    });

    it('full CRUD lifecycle', () => {
        // Empty state
        cy.visit(`/w/${workspaceUuid}/tags`);
        cy.contains('Tags').should('be.visible');
        cy.contains('Nenhuma tag cadastrada').should('be.visible');

        // Create
        cy.contains('Nova Tag').click();
        cy.url().should('include', '/tags/create');

        cy.get('#name').type('Urgente');
        cy.contains('Criar Tag').click();

        cy.url().should('include', '/tags');
        cy.contains('Urgente').should('be.visible');

        // Duplicate name error
        cy.contains('Nova Tag').click();
        cy.get('#name').type('Urgente');
        cy.contains('Criar Tag').click();
        cy.contains('Já existe uma tag com esse nome').should('be.visible');

        // Edit
        cy.visit(`/w/${workspaceUuid}/tags`);
        cy.contains('Urgente')
            .closest('[data-slot="card"]')
            .contains('Editar')
            .click();

        cy.url().should('include', '/tags/');
        cy.get('#name').clear().type('Urgente Editada');
        cy.contains('Salvar').click();

        cy.url().should('include', '/tags');
        cy.contains('Urgente Editada').should('be.visible');

        // Delete
        cy.visit(`/w/${workspaceUuid}/tags/create`);
        cy.get('#name').type('Para Excluir');
        cy.contains('Criar Tag').click();

        cy.url().should('include', '/tags');
        cy.contains('Para Excluir').should('be.visible');
        cy.contains('Para Excluir')
            .closest('[data-slot="card"]')
            .contains('button', 'Excluir')
            .click({ force: true });

        cy.contains('Para Excluir').should('not.exist');
    });
});
