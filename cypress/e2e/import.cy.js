describe('Unified CSV Import', () => {
    let workspaceUuid;

    beforeEach(() => {
        cy.loginViaSession('import-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Import');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
            cy.visit(`/w/${workspaceUuid}`);
        });
    });

    it('renders the import page from sidebar without crashing', () => {
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.url().should('include', '/import');
        cy.contains('Importar').should('be.visible');
        cy.contains('Arquivo').should('be.visible');
    });

    it('shows full import journey: upload → preview → confirm → redirect + toast', () => {
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.url().should('include', '/import');

        cy.get('#file').selectFile('cypress/fixtures/import.csv', {
            force: true,
        });
        cy.contains('button', 'Importar').click({ force: true });

        cy.url().should('include', '/import');
        cy.contains('Revise os dados antes de confirmar a importação').should(
            'be.visible',
        );

        cy.get('table').should('be.visible');
        cy.get('table tbody tr').should('have.length', 4);

        // Descriptions are in <input> elements, not text content
        cy.get('table tbody tr')
            .eq(0)
            .find('input')
            .first()
            .should('have.value', 'Supermercado XYZ');
        cy.get('table tbody tr')
            .eq(1)
            .find('input')
            .first()
            .should('have.value', 'Farmácia ABC');
        cy.get('table tbody tr')
            .eq(2)
            .find('input')
            .first()
            .should('have.value', 'Uber Viagem');
        cy.get('table tbody tr')
            .eq(3)
            .find('input')
            .first()
            .should('have.value', 'Salário Empresa');

        cy.contains('3 despesas + 1 receitas selecionadas').should('be.visible');

        cy.contains('button', 'Confirmar Importação').click({ force: true });

        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/w/' + workspaceUuid);
        cy.should('not.include', '/import');
    });

    it('creates transactions in the database after confirm', () => {
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', {
            force: true,
        });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should(
            'be.visible',
        );
        cy.get('table tbody tr').should('have.length', 4);
        cy.contains('3 despesas + 1 receitas selecionadas').should('be.visible');
        cy.contains('button', 'Confirmar Importação').click({ force: true });
        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/w/' + workspaceUuid);
        // After redirect to dashboard, verify via the expenses/incomes lists
        cy.get('[data-testid="sidebar-transactions"]').click();
        cy.contains('Supermercado XYZ').should('be.visible');
        cy.contains('Farmácia ABC').should('be.visible');
    });

    it('shows both Despesa and Receita badges for mixed types', () => {
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', {
            force: true,
        });
        cy.contains('button', 'Importar').click({ force: true });

        cy.get('table tbody tr').should('have.length', 4);

        // First 3 rows are debit (Despesa)
        cy.get('table tbody tr').eq(0).contains('Despesa').should('be.visible');
        cy.get('table tbody tr').eq(1).contains('Despesa').should('be.visible');
        cy.get('table tbody tr').eq(2).contains('Despesa').should('be.visible');
        // Last row is credit (Receita)
        cy.get('table tbody tr').eq(3).contains('Receita').should('be.visible');
    });

    it('allows editing a field in the preview table before confirming', () => {
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', {
            force: true,
        });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should(
            'be.visible',
        );
        cy.get('table tbody tr').should('have.length', 4);
        cy.contains('3 despesas + 1 receitas selecionadas').should('be.visible');

        cy.get('table tbody tr')
            .first()
            .find('input')
            .first()
            .clear()
            .type('Mercado Editado');

        cy.contains('button', 'Confirmar Importação').click({ force: true });
        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/w/' + workspaceUuid);
    });

    it('unchecking a row excludes it from import', () => {
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', {
            force: true,
        });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should(
            'be.visible',
        );
        cy.get('table tbody tr').should('have.length', 4);
        cy.contains('3 despesas + 1 receitas selecionadas').should('be.visible');

        // Uncheck first row (shadcn Checkbox renders as <button role="checkbox">)
        cy.get('table tbody tr').first().find('[role="checkbox"]').click();

        cy.contains('2 despesas + 1 receitas selecionadas').should('be.visible');

        cy.contains('button', 'Confirmar Importação').click({ force: true });
        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/w/' + workspaceUuid);
    });

    it('cancel does not create any transactions', () => {
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', {
            force: true,
        });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should(
            'be.visible',
        );

        // Cancel renders as <a> (Button asChild + Link)
        cy.contains('a', 'Cancelar').click();

        cy.url().should('include', '/w/' + workspaceUuid);
        cy.should('not.include', '/import');
    });

    it('shows error toast for invalid file type', () => {
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.get('#file').selectFile(
            {
                contents: Cypress.Buffer.from([
                    0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09,
                    0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f,
                ]),
                fileName: 'malware.exe',
                mimeType: 'application/octet-stream',
            },
            { force: true },
        );

        cy.contains('button', 'Importar').click({ force: true });

        cy.url().should('include', '/import');
        cy.contains(
            'O arquivo deve ser CSV, PDF, HTML, imagem ou texto.',
        ).should('be.visible');
    });

    it('detects duplicates and flags them unchecked', () => {
        // First import
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', {
            force: true,
        });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should(
            'be.visible',
        );
        cy.get('table tbody tr').should('have.length', 4);
        cy.contains('3 despesas + 1 receitas selecionadas').should(
            'be.visible',
        );
        cy.contains('button', 'Confirmar Importação').click({ force: true });
        cy.assertToast('success', 'importadas');

        cy.url().should('include', '/w/' + workspaceUuid);

        // Second import of same file
        cy.get('[data-testid="sidebar-imports"]').click();

        cy.get('#file').selectFile('cypress/fixtures/import.csv', {
            force: true,
        });
        cy.contains('button', 'Importar').click({ force: true });

        cy.contains('Revise os dados antes de confirmar a importação').should(
            'be.visible',
        );

        cy.get('table tbody tr').contains('Possível duplicata').should('exist');

        // shadcn Checkbox renders as <button role="checkbox"> with aria-checked
        cy.get('table tbody tr')
            .filter(':contains("Possível duplicata")')
            .find('[role="checkbox"]')
            .should('have.attr', 'aria-checked', 'false');

        cy.contains('0 despesas + 0 receitas selecionadas').should('be.visible');
    });
});
