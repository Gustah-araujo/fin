describe('Scheduled Financial Analysis (AI agent)', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('ai-financial-analysis-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E AI Analysis');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('ai-financial-analysis-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    // 1. Smoke test: Bell icon renders
    it('renders the notification bell without crashing', () => {
        cy.get('button svg.lucide-bell').should('be.visible');
    });

    // 2. Main contract: Trigger analysis (default 2 insights), verify notification content
    it('creates a financial insight notification with the correct title and message', () => {
        cy.clearNotifications(workspaceUuid);
        cy.triggerFinancialAnalysis(workspaceUuid);
        cy.visit(`/w/${workspaceUuid}`);

        cy.get('svg.lucide-bell').closest('button').click();
        cy.contains('Análise financeira concluída').should('be.visible');
        cy.contains(
            '2 novos insights financeiros identificados para E2E AI Analysis.',
        ).should('be.visible');
    });

    // 3. Mark as read: Click "Marcar como lida", verify toast
    it('marks the financial insight notification as read', () => {
        cy.clearNotifications(workspaceUuid);
        cy.triggerFinancialAnalysis(workspaceUuid);
        cy.visit(`/w/${workspaceUuid}`);

        cy.get('svg.lucide-bell').closest('button').click();
        cy.contains('Marcar como lida').first().click();
        cy.assertToast('success', 'marcada como lida');
    });

    // 4. Empty insights: No notification should be created
    it('does not create a notification when there are no insights', () => {
        cy.clearNotifications(workspaceUuid);
        cy.triggerFinancialAnalysis(workspaceUuid, []);
        cy.visit(`/w/${workspaceUuid}`);

        cy.get('svg.lucide-bell').closest('button').click();
        cy.contains('Nenhuma notificação no momento.').should('be.visible');
    });
});
