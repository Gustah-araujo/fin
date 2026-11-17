describe('Fin Memory Integration', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('fin-memory-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Fin Memory');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('fin-memory-session');

        cy.visit(`/w/${workspaceUuid}/fin`);
    });

    // finCleanup deletes conversations/runs/pending mutations but NOT agent
    // memories — that's fine since each run of the suite uses a fresh
    // workspace created in before().
    afterEach(() => {
        cy.finCleanup(workspaceUuid);
    });

    // 1. Smoke test: page renders without crashing
    it('renders the Fin assistant page without crashing', () => {
        cy.get('[data-testid="sidebar-fin"]').should('be.visible');
        cy.contains('Assistente Fin').should('be.visible');
    });

    // 2. Memory persists across conversations: a preference declared in
    // conversation 1 is still reflected in the assistant's answer in a
    // brand-new conversation with clean history.
    it('remembers a preference declared in a previous conversation', () => {
        // Step 1 — conversation 1: user declares a preference. The direct
        // flow creates + completes the conversation in one shot.
        cy.finResponse(workspaceUuid, {
            message: 'Prefiro registrar gastos de delivery como Lazer',
            response:
                'Entendi! Vou lembrar que você prefere categorizar delivery como Lazer.',
            title: 'Preferência',
        });

        // Step 2 — register the memory through the platform's insight
        // pipeline. The testing route fakes ScheduledFinancialAnalysisAgent
        // and persists each insight as a real AgentMemory row in this
        // workspace.
        cy.get('meta[name="csrf-token"]')
            .then((meta) => {
                return cy.request({
                    method: 'POST',
                    url: `/w/${workspaceUuid}/testing/financial-analysis`,
                    headers: {
                        'X-CSRF-TOKEN': meta.attr('content'),
                    },
                    body: {
                        insights: [
                            {
                                type: 'insight',
                                title: 'Preferência de categorização',
                                description:
                                    'Usuário prefere categorizar gastos de delivery como Lazer.',
                                severity: 'low',
                                data: null,
                            },
                        ],
                    },
                });
            })
            .then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.insights).to.eq(1);
            });

        // Step 3 — start a NEW conversation with clean history via the UI.
        cy.intercept('POST', '**/fin/conversations').as('startConversation');

        cy.get('[data-testid="fin-new-conversation"]').click();
        cy.get('[data-testid="fin-input"]').type(
            'Como devo categorizar gastos de delivery?',
        );
        cy.get('[data-testid="fin-send"]').click();

        cy.wait('@startConversation');
        cy.get('[data-testid="fin-conversation-item"]').should(
            'have.length',
            1,
        );
        cy.get('[data-testid="fin-input"]').should('be.disabled');

        // Step 4 — complete the run with a scripted response that leverages
        // the memory. The server-side proof that the memory was injected
        // into the new conversation's prompt is covered by PHPUnit
        // (tests/Feature/Ai/AgentMemoryCrossAgentTest.php); this E2E
        // validates the full UX journey works end-to-end.
        cy.finResponse(workspaceUuid, {
            response:
                'Com base no que já sei de você, gastos de **delivery** devem ser categorizados como **Lazer**.',
        });

        // Step 5 — the memory-aware response renders.
        cy.contains('Com base no que já sei de você', {
            timeout: 10000,
        }).should('be.visible');
        cy.contains('delivery').should('be.visible');

        // Input re-enables after completion.
        cy.get('[data-testid="fin-input"]').should('not.be.disabled');
    });
});

describe('Fin Attachment Persistence', () => {
    let workspaceUuid;

    afterEach(() => {
        if (workspaceUuid) {
            cy.finCleanup(workspaceUuid);
        }
    });

    // Multi-round attachment persistence: the backend re-sends the
    // attachments of ALL previous user messages (within the history window)
    // to the LLM on every round, so the user can answer a confirmation
    // question without re-uploading the file. This E2E validates the UI
    // journey: the round-1 attachment chip stays visible in the history
    // after a round-2 message that carries no files.
    it('keeps the first message attachment visible in history after a follow-up message without files', () => {
        cy.registerAndCreateWorkspace('Fin Anexos Workspace').then((uuid) => {
            workspaceUuid = uuid;

            cy.visit(`/w/${uuid}/fin`);

            // Intercept the polling GET instead of the multipart POST: same
            // reason as fin.cy.js — cy.intercept re-serializes request bodies
            // as UTF-8 text, which corrupts binary upload payloads. Waiting
            // for the first poll proves the POST completed.
            cy.intercept('GET', '**/fin/runs/*').as('pollRun');

            cy.get('[data-testid="fin-new-conversation"]').click();
            cy.get('[data-testid="fin-attachment-input"]').selectFile(
                'cypress/fixtures/sample.pdf',
                { force: true },
            );

            cy.get('[data-testid="fin-attachment-chip"]')
                .should('be.visible')
                .and('contain', 'sample.pdf');

            // Round 1: send the message WITH the file attached.
            cy.get('[data-testid="fin-input"]').type(
                'Analise este extrato e cadastre as despesas',
            );
            cy.get('[data-testid="fin-send"]').click();

            cy.wait('@pollRun');
            cy.get('[data-testid="fin-conversation-item"]').should(
                'have.length',
                1,
            );
            cy.get('[data-testid="fin-attachment-chip"]').should('not.exist');

            // Round 1 completes via the real FinAssistantService with a
            // faked agent response.
            cy.finResponse(uuid, {
                response:
                    'Encontrei as despesas no anexo. Confirma o cadastro?',
            });

            cy.contains('Encontrei as despesas no anexo.', {
                timeout: 10000,
            }).should('be.visible');

            // The user message's attachment chip is rendered in history.
            cy.get('[data-testid="fin-message-attachments"]')
                .should('be.visible')
                .and('contain', 'sample.pdf');

            // Round 2: follow-up WITHOUT re-uploading the file (the bug
            // scenario — before the fix this round lost the attachment).
            cy.get('[data-testid="fin-input"]').should('not.be.disabled');
            cy.get('[data-testid="fin-input"]').type('Pode cadastrar');
            cy.get('[data-testid="fin-send"]').click();

            cy.wait('@pollRun');

            // Round 2 completes via the real FinAssistantService as well.
            cy.finResponse(uuid, {
                response:
                    'Cadastro efetuado com base no extrato anexado anteriormente.',
            });

            cy.contains('Cadastro efetuado com base no extrato', {
                timeout: 10000,
            }).should('be.visible');

            // Exactly one user message carries attachments, and it is still
            // the round-1 one with sample.pdf.
            cy.get('[data-testid="fin-message-attachments"]')
                .should('have.length', 1)
                .and('contain', 'sample.pdf');

            // The round-2 user message has no attachment chip of its own.
            cy.get('[data-testid="message-bubble"][data-role="user"]')
                .contains('Pode cadastrar')
                .find('[data-testid="fin-message-attachments"]')
                .should('not.exist');
        });
    });
});
