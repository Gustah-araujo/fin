describe('Notification Center', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('notifications-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Notifications');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('notifications-session');

        cy.visit(`/w/${workspaceUuid}`);
    });

    // 1. Smoke test: Bell icon renders
    it('renders the notification bell in the header', () => {
        cy.get('button svg.lucide-bell').should('be.visible');
    });

    // 2. Badge display: Create 3 unread, verify badge shows "3"
    it('displays the unread badge count', () => {
        cy.clearNotifications(workspaceUuid);
        cy.seedNotifications(workspaceUuid, 3);
        cy.visit(`/w/${workspaceUuid}`);

        cy.contains('button', '3').should('be.visible');
    });

    // 3. Open drawer: Click bell, verify drawer opens
    it('opens the notification drawer when bell is clicked', () => {
        cy.clearNotifications(workspaceUuid);
        cy.seedNotifications(workspaceUuid, 2, 'Test Notification');
        cy.visit(`/w/${workspaceUuid}`);

        cy.get('button svg.lucide-bell').click();
        cy.contains('Notificações').should('be.visible');
        cy.contains('Test Notification').should('be.visible');
    });

    // 4. Mark as read: Click "Marcar como lida", verify toast
    it('marks a single notification as read', () => {
        cy.clearNotifications(workspaceUuid);
        cy.seedNotifications(workspaceUuid, 3);
        cy.visit(`/w/${workspaceUuid}`);

        cy.get('button svg.lucide-bell').click();
        cy.contains('Marcar como lida').first().click();
        cy.assertToast('success', 'marcada como lida');
    });

    // 5. Mark all as read: Click "Marcar todas como lidas", verify toast
    it('marks all notifications as read', () => {
        cy.clearNotifications(workspaceUuid);
        cy.seedNotifications(workspaceUuid, 3);
        cy.visit(`/w/${workspaceUuid}`);

        cy.get('button svg.lucide-bell').click();
        cy.contains('Marcar todas como lidas').click();
        cy.assertToast('success', 'Todas');
    });

    // 6. Action button: Create notification with action_url, verify button renders
    it('shows the action button when a notification has an action', () => {
        cy.clearNotifications(workspaceUuid);
        cy.request({
            method: 'POST',
            url: `/w/${workspaceUuid}/testing/notifications`,
            body: {
                count: 1,
                title: 'Notification with Action',
                message: 'Click the action button',
                action_url: `/w/${workspaceUuid}`,
                action_text: 'Go to Dashboard',
            },
        });
        cy.visit(`/w/${workspaceUuid}`);

        cy.get('button svg.lucide-bell').click();
        cy.contains('button', 'Go to Dashboard').should('be.visible');
    });

    // 7. Empty state: With no notifications, verify empty state message
    it('shows empty state when no notifications exist', () => {
        cy.clearNotifications(workspaceUuid);
        cy.visit(`/w/${workspaceUuid}`);

        cy.get('button svg.lucide-bell').click();
        cy.contains('Nenhuma notificação no momento').should('be.visible');
    });
});
