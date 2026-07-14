describe('Tag CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('tags-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Tags');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('tags-session');
    });

    it('shows empty tags list', () => {
        cy.visit(`/w/${workspaceUuid}/tags`);
        cy.contains('Tags').should('be.visible');
        cy.contains('Nenhuma tag cadastrada').should('be.visible');
    });

    it('creates a tag', () => {
        cy.visit(`/w/${workspaceUuid}/tags`);
        cy.contains('Nova Tag').click();
        cy.url().should('include', '/tags/create');

        cy.get('#name').type('Urgente');
        cy.contains('Criar Tag').click();

        cy.url().should('include', '/tags');
        cy.contains('Urgente').should('be.visible');
    });

    it('shows duplicate name error', () => {
        cy.visit(`/w/${workspaceUuid}/tags/create`);
        cy.get('#name').type('Urgente');
        cy.contains('Criar Tag').click();

        cy.contains('Já existe uma tag com esse nome').should('be.visible');
    });

    it('edits a tag', () => {
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
    });

    it('deletes a tag', () => {
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
