describe('Workspace selection', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('select-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Select');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('select-session');
    });

    it('selects a workspace and lands on its dashboard', () => {
        cy.visit('/workspace/select');
        cy.contains('E2E Select').should('be.visible');

        cy.contains('E2E Select').click();
        cy.assertToast('success', 'ativado');

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.contains('Dashboard').should('be.visible');
    });

    it('redirects root to the selected workspace dashboard', () => {
        cy.visit('/');

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.contains('Dashboard').should('be.visible');
    });
});
