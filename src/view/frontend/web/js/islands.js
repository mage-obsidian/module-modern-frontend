/**
 * Page-level island bootstrap. Loaded once per page (see Block\IslandsRuntime),
 * it discovers the markers emitted by `renderVueComponent` and mounts each as a
 * Vue island.
 *
 * Vue and the i18n plugin are imported lazily and only when at least one marker
 * is present, so pages without islands pay nothing — not even the Vue runtime.
 * The reusable discovery/hydration logic lives in the engine
 * (`mage-obsidian/runtime/islands.ts`); here we provide the concrete browser
 * side effects: dynamic component import, app creation, plugin wiring, and
 * viewport observation for the default "visible" (lazy) strategy.
 */
import { hydrateAll } from 'mage-obsidian/runtime/islands.ts';

function observeOnce(element, onVisible) {
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                onVisible();
            }
        }
    });
    observer.observe(element);
}

async function start() {
    const markers = document.querySelectorAll('[data-mage-island]');
    if (markers.length === 0) {
        return;
    }

    const [{ createApp }, { default: obsidianI18n }] = await Promise.all([
        import('vue'),
        import('./i18n.js'),
    ]);

    hydrateAll(markers, {
        // The component URL is only known at runtime (PHP resolves it per island),
        // so this is an intentionally un-analyzable dynamic import.
        importComponent: (source) => import(/* @vite-ignore */ source),
        createApp,
        configureApp: (app) => app.use(obsidianI18n),
        observe: observeOnce,
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    void start();
}
