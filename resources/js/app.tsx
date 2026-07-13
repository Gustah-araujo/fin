import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { route as ziggyRoute } from 'ziggy-js';

declare global {
    const route: typeof ziggyRoute;
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true });
        return pages[`./Pages/${name}.tsx`] as { default: React.ComponentType };
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
