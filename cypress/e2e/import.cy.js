describe('CSV Import', () => {
    let workspaceUuid;

    beforeEach(() => {
        cy.loginViaSession('import-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Import');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });

        cy.visit(`/w/${workspaceUuid}`);
    });

    it('renders the import page without crashing', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.url().should('include', '/transactions/import');
        cy.contains('Importar Despesas').should('be.visible');
        cy.contains('Arquivo CSV').should('be.visible');
    });

    it('shows full import journey: upload → preview → confirm → redirect + toast', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.url().should('include', '/transactions/import');

        cy.get('#file').selectFile('cypress/fixtures/import.csv', { force: true });
        cy.contains('button', 'Importar').click({ force: true });

        cy.url().should('include', '/transactions/import');
        cy.contains('Revise os dados antes de confirmar a importação').should('be.visible');

        cy.get('table').should('be.visible');
        cy.get('table tbody tr').should('have.length', 3);
        cy.contains('Supermercado XYZ').should('be.visible');
        cy.contains('Farmácia ABC').should('be.visible');
        cy.contains('Uber Viagem').should('be.visible');

        cy.contains('3 de 3 selecionadas').should('be.visible');

        cy.contains('button', 'Confirmar Importação').click({ force: true });

        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/transactions');
        cy.should('not.include', '/import');
    });

    it('creates transactions in the database after confirm', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', { force: true });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should('be.visible');
        cy.contains('button', 'Confirmar Importação').click({ force: true });
        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/transactions');
        cy.contains('Supermercado XYZ').should('be.visible');
        cy.contains('Farmácia ABC').should('be.visible');
        cy.contains('Uber Viagem').should('be.visible');
    });

    it('allows editing a field in the preview table before confirming', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', { force: true });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should('be.visible');

        cy.get('table tbody tr')
            .first()
            .find('input[type="text"]')
            .clear()
            .type('Mercado Editado');

        cy.contains('button', 'Confirmar Importação').click({ force: true });
        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/transactions');
        cy.contains('Mercado Editado').should('be.visible');
    });

    it('unchecking a row excludes it from import', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', { force: true });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should('be.visible');

        cy.get('table tbody tr').first().find('input[type="checkbox"]').uncheck();

        cy.contains('2 de 3 selecionadas').should('be.visible');

        cy.contains('button', 'Confirmar Importação').click({ force: true });
        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/transactions');
        cy.contains('Farmácia ABC').should('be.visible');
    });

    it('cancel does not create any transactions', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', { force: true });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should('be.visible');

        cy.contains('button', 'Cancelar').click();

        cy.url().should('include', '/transactions');
        cy.should('not.include', '/import');
        cy.contains('Supermercado XYZ').should('not.exist');
        cy.contains('Uber Viagem').should('not.exist');
    });

    it('shows error toast for invalid file type', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.get('#file').selectFile({
            contents: Cypress.Buffer.from('not a csv'),
            fileName: 'test.xlsx',
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        }, { force: true });

        cy.contains('button', 'Importar').click({ force: true });

        cy.url().should('include', '/transactions/import');
        cy.contains('O arquivo deve ser um CSV').should('be.visible');
    });

    it('detects duplicates and flags them unchecked', () => {
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', { force: true });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should('be.visible');
        cy.contains('button', 'Confirmar Importação').click({ force: true });
        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/transactions');
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Importar Despesas').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', { force: true });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should('be.visible');

        cy.get('table tbody tr').contains('Possível duplicata').should('exist');

        cy.get('table tbody tr')
            .filter(':contains("Possível duplicata")')
            .find('input[type="checkbox"]')
            .should('not.be.checked');

        cy.contains('0 de 3 selecionadas').should('be.visible');
    });

    it('supports income import route', () => {
        cy.get('[data-testid="sidebar-incomes"]').click();
        cy.contains('Importar Receitas').click();

        cy.url().should('include', '/incomes/import');
        cy.contains('Importar Receitas').should('be.visible');
        cy.contains('Arquivo CSV').should('be.visible');
    });
});
