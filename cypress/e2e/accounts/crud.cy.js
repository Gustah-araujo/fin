describe('Account CRUD', () => {
    let workspaceUuid;

    before(() => {
        const email = `e2e-accounts-${Date.now()}@example.com`;

        cy.visit('/register');
        cy.get('#name').type('Account Tester');
        cy.get('#email').type(email);
        cy.get('#password').type('password123');
        cy.get('#password_confirmation').type('password123');
        cy.get('button[type="submit"]').click();

        cy.wait(1000);
        cy.request(`http://localhost:8026/api/v1/search?kind=to&query=${encodeURIComponent(email)}`).then((resp) => {
            const msg = (resp.body.messages || [])[0];
            if (!msg) throw new Error(`No message for ${email}`);
            return cy.request(`http://localhost:8026/api/v1/message/${msg.ID}`);
        }).then((resp) => {
            const html = resp.body.HTML || resp.body.Text || '';
            const match = html.match(/href="([^"]*verify-email[^"]*)"/i);
            cy.visit(match[1].replace(/&amp;/g, '&'));
        });

        cy.url().should('include', '/workspace');
        cy.get('#name').type('Workspace Accounts');
        cy.get('button[type="submit"]').click();
        cy.url().should('match', /\/w\/([a-f0-9-]+)/).then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    it('creates an account and sees it in the list', () => {
        cy.visit(`/w/${workspaceUuid}/accounts`);

        cy.contains('Nova Conta').click();
        cy.url().should('include', '/accounts/create');

        cy.get('#name').type('Conta Teste');
        cy.get('#type').click();
        cy.contains('Corrente').click();
        cy.get('#initial_balance').type('1000');
        cy.contains('Criar Conta').click();

        cy.url().should('include', '/accounts');
        cy.contains('Conta Teste').should('be.visible');
        cy.contains('Corrente').should('be.visible');
    });

    it('shows validation errors on empty submission', () => {
        cy.visit(`/w/${workspaceUuid}/accounts/create`);

        cy.contains('Criar Conta').click();

        cy.contains('O nome da conta é obrigatório').should('be.visible');
        cy.contains('O saldo inicial é obrigatório').should('be.visible');
    });

    it('edits an existing account', () => {
        cy.visit(`/w/${workspaceUuid}/accounts/create`);
        cy.get('#name').type('Para Editar');
        cy.get('#type').click();
        cy.contains('Corrente').click();
        cy.get('#initial_balance').type('500');
        cy.contains('Criar Conta').click();

        cy.url().should('include', '/accounts');
        cy.contains('Para Editar').should('be.visible');
        cy.contains('Para Editar')
            .parents('[class*="Card"]')
            .contains('Editar')
            .click();

        cy.get('#name').clear().type('Conta Editada');
        cy.contains('Salvar').click();

        cy.url().should('include', '/accounts');
        cy.contains('Conta Editada').should('be.visible');
    });

    it('deletes an account', () => {
        cy.visit(`/w/${workspaceUuid}/accounts/create`);
        cy.get('#name').type('Para Excluir');
        cy.get('#type').click();
        cy.contains('Poupança').click();
        cy.get('#initial_balance').type('200');
        cy.contains('Criar Conta').click();

        cy.url().should('include', '/accounts');
        cy.contains('Para Excluir').should('be.visible');
        cy.contains('Para Excluir')
            .parents('[class*="Card"]')
            .contains('Excluir')
            .click();

        cy.contains('Para Excluir').should('not.exist');
    });
});
