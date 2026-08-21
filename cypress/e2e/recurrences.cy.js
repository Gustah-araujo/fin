describe('Recurrence management', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('recurrences-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Recurrences');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.get('#type').click();
            cy.contains('Corrente').click();
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
        });
    });

    beforeEach(() => {
        cy.loginViaSession('recurrences-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('creates a recurring income and shows it on the recurrences page', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Salário');
        cy.get('#value').type('3000');

        cy.get('#account_id').click();
        cy.contains('[role="option"]', 'Conta Principal').click();

        cy.get('#category_id').click();
        cy.contains('[role="option"]', 'Sem Categoria').click();

        cy.get('[data-slot="switch"]').click();

        cy.contains('Criar Receita').click({ force: true });

        cy.get('[data-testid="sidebar-recurrences"]').click();
        cy.contains('Salário').should('be.visible');
        cy.contains('Ativa').should('be.visible');
    });

    it('pauses and reactivates a recurrence', () => {
        cy.get('[data-testid="sidebar-recurrences"]').click();

        cy.contains('Salário')
            .closest('[data-slot="card"]')
            .contains('Pausar')
            .click({ force: true });

        cy.contains('Salário')
            .closest('[data-slot="card"]')
            .contains('Pausada')
            .should('be.visible');

        cy.contains('Salário')
            .closest('[data-slot="card"]')
            .contains('Reativar')
            .click({ force: true });

        cy.contains('Salário')
            .closest('[data-slot="card"]')
            .contains('Ativa')
            .should('be.visible');
    });
});
