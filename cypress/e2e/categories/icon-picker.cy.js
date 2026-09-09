describe('IconPicker', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('icon-picker-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E IconPicker');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('icon-picker-session');
        cy.visit(`/w/${workspaceUuid}/categories/create`);
    });

    it('opens popover when clicking the trigger', () => {
        cy.get('[role="combobox"]').click();
        cy.get('input[placeholder="Buscar ícone..."]').should('be.visible');
    });

    it('selects an icon and shows its name below the trigger', () => {
        cy.get('[role="combobox"]').click();
        cy.get('button[aria-label="Shopping Cart"]').click();

        cy.get('[role="combobox"]').should(
            'have.attr',
            'aria-label',
            'Shopping Cart',
        );
        cy.contains('Shopping Cart').should('be.visible');
    });

    it('filters icons with search', () => {
        cy.get('[role="combobox"]').click();
        cy.get('input[placeholder="Buscar ícone..."]').type('wallet');

        cy.get('button[aria-label="Wallet"]').should('be.visible');
        cy.get('button[aria-label="Shopping Cart"]').should('not.exist');
    });

    it('shows empty state when no icons match search', () => {
        cy.get('[role="combobox"]').click();
        cy.get('input[placeholder="Buscar ícone..."]').type('xyznonexistent');

        cy.contains('Nenhum ícone encontrado').should('be.visible');
    });

    it('creates a category with selected icon', () => {
        cy.get('#name').type('Compras Icon');
        cy.get('#type').click();
        cy.contains('[role="option"]', 'Despesa').click();

        cy.get('[role="combobox"]').click();
        cy.get('button[aria-label="Shopping Cart"]').click();
        cy.contains('Shopping Cart').should('be.visible');

        cy.contains('Criar Categoria').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/categories');
        cy.contains('Compras Icon').should('be.visible');
    });

    it('shows selected icon when editing a category', () => {
        cy.get('#name').type('Viajem Icon');
        cy.get('#type').click();
        cy.contains('[role="option"]', 'Despesa').click();

        cy.get('[role="combobox"]').click();
        cy.get('button[aria-label="Plane"]').click();
        cy.contains('Criar Categoria').click({ force: true });
        cy.assertToast('success', 'criada');

        cy.url().should('include', '/categories');
        cy.contains('Viajem Icon')
            .closest('[data-slot="card"]')
            .contains('Editar')
            .click({ force: true });

        cy.get('[role="combobox"]').should(
            'have.attr',
            'aria-label',
            'Plane',
        );
    });
});
