describe('Category CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('categories-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Categories');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('categories-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('shows categories list with defaults', () => {
        cy.get('[data-testid="sidebar-categories"]').click();
        cy.contains('Categorias').should('be.visible');
        cy.contains('Sem Categoria').should('be.visible');
        cy.contains('Padrão').should('be.visible');
    });

    it('creates a category', () => {
        cy.get('[data-testid="sidebar-categories"]').click();
        cy.contains('Nova Categoria').click({ force: true });
        cy.url().should('include', '/categories/create');

        cy.get('#name').type('Alimentação');
        cy.get('#type').click();
        cy.contains('[role="option"]', 'Despesa').click();
        cy.contains('Criar Categoria').click({ force: true });

        cy.url().should('include', '/categories');
        cy.contains('Alimentação').should('be.visible');
    });

    it('shows validation errors on create', () => {
        cy.get('[data-testid="sidebar-categories"]').click();
        cy.contains('Nova Categoria').click({ force: true });
        cy.contains('Criar Categoria').click({ force: true });

        cy.contains('O nome da categoria é obrigatório').should('be.visible');
    });

    it('edits a category', () => {
        cy.get('[data-testid="sidebar-categories"]').click();
        cy.contains('Alimentação')
            .closest('[data-slot="card"]')
            .contains('Editar')
            .click({ force: true });

        cy.url().should('include', '/categories/');
        cy.get('#name').clear().type('Alimentação Editada');
        cy.contains('Salvar').click({ force: true });

        cy.url().should('include', '/categories');
        cy.contains('Alimentação Editada').should('be.visible');
    });

    it('deletes a category', () => {
        cy.get('[data-testid="sidebar-categories"]').click();
        cy.contains('Nova Categoria').click({ force: true });
        cy.get('#name').type('Para Excluir');
        cy.get('#type').click();
        cy.contains('[role="option"]', 'Despesa').click();
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
