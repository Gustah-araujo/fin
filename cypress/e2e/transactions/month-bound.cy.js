// The MonthPicker label span rendered by Components/MonthPicker/MonthPicker.tsx.
const MONTH_LABEL = 'span.min-w-\\[160px\\]';

/**
 * Format a YYYY-MM string into the pt-BR month label used by the MonthPicker
 * (e.g. "Setembro de 2026"). Mirrors lib/month.ts formatMonthLabel().
 */
function formatMonthLabel(monthStr) {
    const [year, month] = monthStr.split('-').map(Number);
    const date = new Date(year, month - 1, 1);
    const label = date.toLocaleDateString('pt-BR', {
        month: 'long',
        year: 'numeric',
    });

    return label.charAt(0).toUpperCase() + label.slice(1);
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

describe('Transactions Month-Bound', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('month-bound-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Month Bound');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('month-bound-session');

        cy.visit(`/w/${workspaceUuid}/transactions`);
    });

    it('renders without crashing', () => {
        cy.get(MONTH_LABEL).should('be.visible');
        cy.get(MONTH_LABEL).should('contain', String(new Date().getFullYear()));
    });

    it('shows current month by default', () => {
        const now = new Date();
        const monthLabel = now.toLocaleDateString('pt-BR', {
            month: 'long',
            year: 'numeric',
        });
        const expected =
            monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1);

        cy.get(MONTH_LABEL).should('contain', expected);
    });

    it('navigates to next month', () => {
        selectedMonth().then((month) => {
            const expected = formatMonthLabel(navigateMonth(month, 1));

            cy.get(MONTH_LABEL)
                .parent()
                .find('button')
                .last()
                .click({ force: true });

            cy.get(MONTH_LABEL).should('have.text', expected);
        });
    });

    it('navigates to previous month', () => {
        selectedMonth().then((month) => {
            const expected = formatMonthLabel(navigateMonth(month, -1));

            cy.get(MONTH_LABEL)
                .parent()
                .find('button')
                .first()
                .click({ force: true });

            cy.get(MONTH_LABEL).should('have.text', expected);
        });
    });

    it('preserves month on refresh', () => {
        cy.get(MONTH_LABEL)
            .parent()
            .find('button')
            .last()
            .click({ force: true });

        // Wait for the navigation to land on the new month before capturing.
        cy.location('search').should('include', 'month=');

        cy.get(MONTH_LABEL)
            .invoke('text')
            .then((savedMonth) => {
                const expected = savedMonth.trim();

                cy.reload();

                cy.get(MONTH_LABEL).should('have.text', expected);
            });
    });

    it('action buttons are visible', () => {
        cy.contains('Importar Despesas').should('be.visible');
        cy.contains('Nova Despesa').should('be.visible');
    });
});
