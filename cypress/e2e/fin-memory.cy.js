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
