describe('Fin AI Assistant', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('fin-assistant-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Fin');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('fin-assistant-session');

        cy.visit(`/w/${workspaceUuid}/fin`);
    });

    // 1. Smoke test: page renders without crashing
    it('renders the Fin assistant page without crashing', () => {
        cy.get('[data-testid="sidebar-fin"]').should('be.visible');
        cy.contains('Assistente Fin').should('be.visible');
        cy.contains('Selecione uma conversa ou crie uma nova.').should(
            'be.visible',
        );
    });

    // 2. Lazy persistence: clicking "Nova conversa" never persists anything
    it('does not create a sidebar item until the first message is sent', () => {
        cy.get('[data-testid="fin-new-conversation"]').click();
        cy.get('[data-testid="fin-new-conversation"]').click();
        cy.get('[data-testid="fin-new-conversation"]').click();

        cy.get('[data-testid="fin-input"]').should('be.visible');
        cy.get('[data-testid="fin-conversation-item"]').should('not.exist');
    });

    // 3. Full flow: seeded conversation renders user + assistant messages
    it('renders a completed conversation with messages', () => {
        cy.finResponse(workspaceUuid, {
            message: 'Qual é o meu saldo?',
            response: 'Seu saldo atual é de R$ 5.000,00.',
            title: 'Saldo',
        });

        cy.visit(`/w/${workspaceUuid}/fin`);

        cy.contains('[data-testid="fin-conversation-item"]', 'Saldo').click();
        cy.contains('Qual é o meu saldo?').should('be.visible');
        cy.contains('Seu saldo atual é de R$ 5.000,00.').should('be.visible');
    });

    // 4. Input blocking + polling completion
    it('blocks the input while processing and re-enables after completion', () => {
        cy.intercept('POST', '**/fin/conversations').as('startConversation');

        cy.get('[data-testid="fin-new-conversation"]').click();
        cy.get('[data-testid="fin-input"]').type(
            'Quanto gastei em alimentação?',
        );
        cy.get('[data-testid="fin-send"]').click();

        cy.wait('@startConversation');
        cy.get('[data-testid="fin-conversation-item"]').should(
            'have.length',
            1,
        );
        cy.get('[data-testid="fin-input"]').should('be.disabled');
        cy.get('[data-testid="fin-typing"]').should('be.visible');

        cy.finResponse(workspaceUuid, {
            response: 'Você gastou R$ 150,00 em alimentação.',
        });

        cy.contains('Você gastou R$ 150,00 em alimentação.', {
            timeout: 10000,
        }).should('be.visible');
        cy.get('[data-testid="fin-input"]').should('not.be.disabled');
    });

    // 5. Error toast on failed run
    it('shows an error toast when the assistant run fails', () => {
        cy.intercept('POST', '**/fin/conversations').as('startConversation');

        cy.get('[data-testid="fin-new-conversation"]').click();
        cy.get('[data-testid="fin-input"]').type('Teste de falha');
        cy.get('[data-testid="fin-send"]').click();

        cy.wait('@startConversation');
        cy.get('[data-testid="fin-conversation-item"]').should(
            'have.length',
            1,
        );

        cy.finResponse(workspaceUuid, { fail: true });

        cy.assertToast('error', 'não conseguiu responder');
    });

    // 6b. Assistant Markdown response renders formatted elements
    it('renders Markdown formatting in assistant responses', () => {
        cy.finResponse(workspaceUuid, {
            message: 'Qual é o meu saldo?',
            response: 'Seu saldo é **R$ 5.000,00**.\n\n- Item 1\n- Item 2',
            title: 'Markdown',
        });

        cy.visit(`/w/${workspaceUuid}/fin`);

        cy.contains(
            '[data-testid="fin-conversation-item"]',
            'Markdown',
        ).click();

        cy.get('[data-testid="message-bubble"][data-role="assistant"]')
            .find('strong')
            .should('contain', 'R$ 5.000,00');

        cy.get('[data-testid="message-bubble"][data-role="assistant"]')
            .find('li')
            .should('have.length.at.least', 2);
    });

    // 6c. Sidebar title updates without a page refresh
    it('updates the conversation title in the sidebar without a refresh', () => {
        cy.intercept('POST', '**/fin/conversations').as('startConversation');

        cy.get('[data-testid="fin-new-conversation"]').click();
        cy.get('[data-testid="fin-input"]').type('Qual é o meu saldo?');
        cy.get('[data-testid="fin-send"]').click();

        cy.wait('@startConversation');
        cy.get('[data-testid="fin-conversation-item"]').should(
            'have.length',
            1,
        );
        cy.get('[data-testid="fin-input"]').should('be.disabled');

        cy.finResponse(workspaceUuid, {
            response: 'Seu saldo é **R$ 5.000,00**.',
            title: 'Meu saldo',
        });

        cy.contains('[data-testid="fin-conversation-item"]', 'Meu saldo', {
            timeout: 10000,
        }).should('be.visible');

        cy.get('[data-testid="fin-input"]').should('not.be.disabled');
    });

    // 6. Delete conversation + toast
    it('deletes a conversation and shows a success toast', () => {
        cy.finResponse(workspaceUuid, {
            message: 'Conversa para excluir',
            response: 'Resposta da conversa para excluir.',
            title: 'Excluir esta',
        });

        cy.visit(`/w/${workspaceUuid}/fin`);

        cy.contains(
            '[data-testid="fin-conversation-item"]',
            'Excluir esta',
        ).click();
        cy.get('[data-testid="fin-delete"]').click();
        cy.get('[data-testid="fin-delete-confirm"]').click();

        cy.assertToast('success', 'excluída');
        cy.contains(
            '[data-testid="fin-conversation-item"]',
            'Excluir esta',
        ).should('not.exist');
    });

    // 7. File upload: stage via hidden input, send with text, chip cleared
    it('stages a file, sends it with a message and clears the staging area', () => {
        // Intercept the polling GET instead of the multipart POST: cy.intercept
        // re-serializes request bodies as UTF-8 text, which corrupts binary
        // upload payloads. The first poll only happens after the POST has
        // been accepted, so waiting for it proves the POST completed.
        cy.intercept('GET', '**/fin/runs/*').as('pollRun');

        cy.get('[data-testid="fin-new-conversation"]').click();
        cy.get('[data-testid="fin-input"]').should('be.visible');

        cy.get('[data-testid="fin-attachment-input"]').selectFile(
            'cypress/fixtures/sample.pdf',
            { force: true },
        );

        cy.get('[data-testid="fin-attachment-chip"]')
            .should('be.visible')
            .and('contain', 'sample.pdf');

        cy.get('[data-testid="fin-input"]').type('Analise o anexo.');
        cy.get('[data-testid="fin-send"]').click();

        cy.wait('@pollRun');
        cy.get('[data-testid="fin-conversation-item"]').should(
            'have.length',
            1,
        );
        cy.get('[data-testid="fin-attachment-chip"]').should('not.exist');

        cy.finResponse(workspaceUuid, {
            response: 'Analisei o arquivo em anexo.',
        });

        cy.contains('Analisei o arquivo em anexo.', { timeout: 10000 }).should(
            'be.visible',
        );
        cy.get('[data-testid="fin-message-attachments"]')
            .should('be.visible')
            .and('contain', 'sample.pdf');
    });

    // 8. File upload: send with only a staged file (no text)
    it('sends a message with only a staged file and no text', () => {
        cy.intercept('GET', '**/fin/runs/*').as('pollRun');

        cy.get('[data-testid="fin-new-conversation"]').click();

        // Binary fixtures must be passed as a Buffer: selectFile reads path
        // strings as UTF-8 text, which corrupts non-ASCII bytes.
        cy.fixture('sample.png', 'base64').then((base64) => {
            cy.get('[data-testid="fin-attachment-input"]').selectFile(
                {
                    contents: Cypress.Buffer.from(base64, 'base64'),
                    fileName: 'sample.png',
                    mimeType: 'image/png',
                },
                { force: true },
            );
        });

        cy.get('[data-testid="fin-attachment-chip"]')
            .should('be.visible')
            .and('contain', 'sample.png');

        cy.get('[data-testid="fin-send"]').click();

        cy.wait('@pollRun');
        cy.get('[data-testid="fin-conversation-item"]').should(
            'have.length',
            1,
        );
        cy.get('[data-testid="fin-attachment-chip"]').should('not.exist');

        cy.finResponse(workspaceUuid, {
            response: 'Arquivo recebido com sucesso.',
        });

        cy.contains('Arquivo recebido com sucesso.', { timeout: 10000 }).should(
            'be.visible',
        );
        cy.get('[data-testid="fin-message-attachments"]')
            .should('be.visible')
            .and('contain', 'sample.png');
    });

    // 9. File upload: non-permitted extension is rejected with an error toast
    it('rejects a file with a non-permitted extension and shows an error toast', () => {
        cy.get('[data-testid="fin-new-conversation"]').click();

        cy.get('[data-testid="fin-attachment-input"]').selectFile(
            'cypress/fixtures/blocked.exe',
            { force: true },
        );

        cy.get('[data-testid="fin-attachment-chip"]').should('not.exist');
        cy.get('[data-testid="fin-send"]').should('be.disabled');

        cy.get('[data-sonner-toast]')
            .should('be.visible')
            .and('contain', 'Tipo de arquivo não permitido.');
    });

    // 10. File upload: staged file can be removed before sending
    it('removes a staged attachment via the chip remove button', () => {
        cy.get('[data-testid="fin-new-conversation"]').click();

        cy.get('[data-testid="fin-attachment-input"]').selectFile(
            'cypress/fixtures/sample.pdf',
            { force: true },
        );

        cy.get('[data-testid="fin-attachment-chip"]')
            .should('be.visible')
            .and('contain', 'sample.pdf');

        cy.get('button[aria-label="Remover anexo sample.pdf"]').click();

        cy.get('[data-testid="fin-attachment-chip"]').should('not.exist');
        cy.get('[data-testid="fin-send"]').should('be.disabled');
    });
});
