describe('Incomes paid on creation', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('incomes-paid-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Incomes Paid');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.selectCombobox('#type', 'Corrente');
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');

            cy.url().should('include', '/accounts');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('incomes-paid-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('renders receitas page without crashing', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Receitas').should('be.visible');
    });

    it('creates a receita already received in the linked account', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Nova Receita').click();
        cy.get('#description').type('Freelance Pago');
        cy.get('#value').type('1000');

        cy.selectCombobox('#account_id', 'Conta Principal');
        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Date defaults to today; mark as already received
        cy.get('#is_paid').click();

        cy.contains('Criar Receita').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/incomes');
        cy.contains('Freelance Pago')
            .should('be.visible')
            .closest('tr')
            .contains('Recebida')
            .should('be.visible');

        // Account balance reflects the received income (5000 + 1000)
        cy.get('[data-testid="sidebar-accounts"]').click();
        cy.contains('Conta Principal')
            .closest('[data-slot="card"]')
            .contains(/R\$\s?6\.000,00/)
            .should('be.visible');
    });
});
