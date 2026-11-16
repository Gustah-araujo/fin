describe('Transactions without account', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('transactions-no-account-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E No Account');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('transactions-no-account-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('renders despesas page without crashing', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Despesas').should('be.visible');
    });

    it('creates a paid expense without an account', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Nova Despesa').click({ force: true });
        cy.url().should('include', '/transactions/create');
        cy.get('#description').should('be.visible');

        cy.get('#description').type('Compra sem conta');
        cy.get('#value').type('150');

        cy.selectCombobox('#account_id', 'Sem conta');
        cy.selectCombobox('#category_id', 'Sem Categoria');

        // Date defaults to today; mark as already paid
        cy.get('#is_paid').click();

        cy.contains('Criar Despesa').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/transactions');
        cy.contains('Compra sem conta')
            .should('be.visible')
            .closest('tr')
            .within(() => {
                // Status column: paid account expense renders "✓"
                cy.contains('✓').should('be.visible');
                // Account column: no linked account renders "—"
                cy.contains('—').should('be.visible');
            });
    });
});
