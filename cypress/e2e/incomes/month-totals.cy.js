// The MonthPicker label span rendered by Components/MonthPicker/MonthPicker.tsx.
const MONTH_LABEL = 'span.min-w-\\[80px\\]';

/**
 * Format a YYYY-MM string into the MM/YYYY label used by the MonthPicker
 * (e.g. "09/2026"). Mirrors lib/month.ts formatMonthLabel().
 */
function formatMonthLabel(monthStr) {
    const [year, month] = monthStr.split('-').map(Number);

    return `${String(month).padStart(2, '0')}/${year}`;
}

/**
 * Offset a YYYY-MM string by the given number of months, handling year
 * boundaries. Mirrors lib/month.ts navigateMonth().
 */
function navigateMonth(monthStr, delta) {
    const [year, month] = monthStr.split('-').map(Number);
    const totalMonths = year * 12 + (month - 1) + delta;
    const nextYear = Math.floor(totalMonths / 12);
    const nextMonth = (totalMonths % 12) + 1;

    return `${nextYear}-${String(nextMonth).padStart(2, '0')}`;
}

/**
 * Resolve the month currently selected in the URL, falling back to the
 * current month when the page is visited without a `?month=` param.
 */
function selectedMonth() {
    return cy.location('search').then((search) => {
        const fromUrl = new URLSearchParams(search).get('month');

        if (fromUrl) {
            return fromUrl;
        }

        const now = new Date();
        const month = String(now.getMonth() + 1).padStart(2, '0');

        return `${now.getFullYear()}-${month}`;
    });
}

describe('Income Month Totals', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('income-month-totals-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Income Totals');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.selectCombobox('#type', 'Corrente');
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');

            cy.url().should('include', '/accounts');
        });
    });

    beforeEach(() => {
        cy.loginViaSession('income-month-totals-session');

        cy.visit(`/w/${workspaceUuid}/incomes`);
    });

    function createIncome(description, value) {
        cy.contains('Nova Receita').click({ force: true });
        cy.url().should('include', '/incomes/create');
        cy.get('#description').type(description);
        cy.get('#value').type(value);

        cy.selectCombobox('#account_id', 'Conta Principal');

        cy.selectCombobox('#category_id', 'Sem Categoria');

        cy.contains('Criar Receita').click({ force: true });
        cy.assertToast('success', 'criada');
        cy.window().then((win) => {
            win.__toastBuffer = [];
        });

        cy.url().should('include', '/incomes');
    }

    function confirmIncome(description) {
        cy.contains(description)
            .closest('tr')
            .contains('Confirmar')
            .click({ force: true });
        cy.assertToast('success', 'confirmada');

        cy.window().then((win) => {
            win.__toastBuffer = [];
        });
    }

    it('renders without crashing', () => {
        cy.contains('Receitas').should('be.visible');
        cy.get(MONTH_LABEL).should('be.visible');
        cy.get('[data-testid="total-incomes"]').should('be.visible');
        cy.get('[data-testid="total-received"]').should('be.visible');
        cy.get('[data-testid="total-to-receive"]').should('be.visible');
    });

    it('shows current month by default', () => {
        const now = new Date();
        const expected = `${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()}`;

        cy.get(MONTH_LABEL).should('contain', expected);
    });

    it('navigates to previous month updating URL and label', () => {
        selectedMonth().then((month) => {
            const expected = navigateMonth(month, -1);
            const expectedLabel = formatMonthLabel(expected);

            cy.get('[data-testid="month-prev"]').click({ force: true });

            cy.location('search').should('include', `month=${expected}`);
            cy.get(MONTH_LABEL).should('have.text', expectedLabel);
        });
    });

    it('navigates to next month updating URL and label', () => {
        selectedMonth().then((month) => {
            const expected = navigateMonth(month, 1);
            const expectedLabel = formatMonthLabel(expected);

            cy.get('[data-testid="month-next"]').click({ force: true });

            cy.location('search').should('include', `month=${expected}`);
            cy.get(MONTH_LABEL).should('have.text', expectedLabel);
        });
    });

    it('shows correct totals for the current month', () => {
        // Isolate in a fresh workspace so totals start at zero and are not
        // affected by transactions created in other workspaces.
        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Income Totals Sums');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            const scopedWorkspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];

            cy.get('[data-testid="sidebar-accounts"]').click();
            cy.contains('Nova Conta').click();
            cy.get('#name').type('Conta Principal');
            cy.selectCombobox('#type', 'Corrente');
            cy.get('#initial_balance').type('5000');
            cy.contains('Criar Conta').click({ force: true });
            cy.assertToast('success', 'criada');

            cy.visit(`/w/${scopedWorkspaceUuid}/incomes`);

            createIncome('Totalizador Recebida 100', '100');
            confirmIncome('Totalizador Recebida 100');

            createIncome('Totalizador Prevista 50', '50');

            cy.get('[data-testid="total-incomes"]').should(
                'contain',
                '150,00',
            );
            cy.get('[data-testid="total-received"]').should(
                'contain',
                '100,00',
            );
            cy.get('[data-testid="total-to-receive"]').should(
                'contain',
                '50,00',
            );
        });
    });

    it('scopes totals and datatable to the selected month', () => {
        cy.get('[data-testid="month-prev"]').click({ force: true });

        // Wait for the month-scoped page load to settle.
        cy.location('search').should('include', 'month=');

        cy.get('[data-testid="total-incomes"]').should('contain', '0,00');
        cy.get('[data-testid="total-received"]').should('contain', '0,00');
        cy.get('[data-testid="total-to-receive"]').should('contain', '0,00');

        cy.contains('Nenhuma receita registrada').should('be.visible');
    });
});
