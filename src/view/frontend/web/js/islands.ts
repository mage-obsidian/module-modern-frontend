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
 *
 * A marker carrying the component's initial state is adopted with `createSSRApp`;
 * one whose contents are a placeholder is cleared and mounted with `createApp`.
 * `createSSRApp` would also fall back to a full mount on an empty container, but
 * warns each time it does, which buries the warnings that matter.
 */
import type { App } from 'vue';
import { hydrateAll, type IslandAnnouncement } from 'mage-obsidian/runtime/islands.ts';
import { diffHydration, formatMismatch } from 'mage-obsidian/runtime/hydrationDiff.ts';
import { LifecycleEvent, type IslandEvent } from 'mage-obsidian/runtime/lifecycleEvents.ts';
import { MutationPhase } from 'mage-obsidian/runtime/mutationEvent.ts';
import events from 'MageObsidian_ModernFrontend::js/events';
import { announcePageReady, bindPageLifecycle } from 'MageObsidian_ModernFrontend::js/lifecycle';

const ISLAND_EVENT: Record<MutationPhase, LifecycleEvent> = {
    [MutationPhase.Before]: LifecycleEvent.IslandMountBefore,
    [MutationPhase.After]: LifecycleEvent.IslandMountAfter,
    [MutationPhase.Failed]: LifecycleEvent.IslandMountFailed,
};

// Values the server cannot predict (a generated id, a locale-formatted number)
// are exempted with Vue's own attribute, so they are cut from both sides rather
// than reported every page load.
const ALLOWED_MISMATCH = '[data-allow-mismatch]';

// Defined by the Vite config. Not `import.meta.env.DEV`: `vite build` always runs
// in production mode, so that one is false even on a developer-mode storefront.
declare const __MAGE_OBSIDIAN_DEV__: boolean;

function observeOnce(element: HTMLElement, onVisible: () => void): void {
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

function islandName(marker: HTMLElement): string {
    const source = marker.dataset.component ?? '(unknown)';
    const path = source.split('/generated/')[1]?.replace(/\.js(\?.*)?$/, '');
    if (!path) {
        return source;
    }
    const [vendor, ...rest] = path.split('/');
    return `${vendor}::${rest.filter((part) => part !== 'components').join('/')}`;
}

interface IslandSnapshot {
    /** Only for a hydration target: the markup Vue is expected to adopt as-is. */
    markup: string | null;
    height: number;
}

function comparableMarkup(element: HTMLElement): string {
    const clone = element.cloneNode(true) as HTMLElement;
    clone.querySelectorAll(ALLOWED_MISMATCH).forEach((node) => node.remove());
    return clone.innerHTML;
}

function takeSnapshot(element: HTMLElement): IslandSnapshot {
    return {
        markup: element.dataset.hydrate ? comparableMarkup(element) : null,
        height: element.offsetHeight,
    };
}

// Two distinct failures, reported apart because they have different fixes: a
// hydration that did not match (the template drifted from the component) and an
// island that resized after the page painted (the shift this all exists to
// prevent). Size is the honest test — an empty container that occupies no space,
// like a drawer or a toast host, moves nothing and is not a defect.
function inspect(element: HTMLElement, snapshot: unknown): void {
    const before = snapshot as IslandSnapshot;

    if (before.markup !== null) {
        const mismatch = diffHydration(before.markup, comparableMarkup(element));
        if (mismatch) {
            console.error(formatMismatch(mismatch, islandName(element)), element);
        }
    }

    const height = element.offsetHeight;
    if (element.dataset.strategy === 'eager' && height !== before.height) {
        console.warn(
            `[MageObsidian] Island "${islandName(element)}" resized on mount ` +
                `(${before.height}px → ${height}px), shifting everything below it. Render its ` +
                'initial state server-side and hydrate — see docs 0105 "Vue Islands".',
            element,
        );
    }
}

function announce(phase: MutationPhase, detail: IslandAnnouncement): void {
    void events.dispatch(ISLAND_EVENT[phase], {
        component: detail.component,
        strategy: detail.strategy,
        element: detail.element,
        durationMs: detail.durationMs,
        error: detail.error,
    } satisfies IslandEvent);
}

async function start(): Promise<void> {
    bindPageLifecycle();

    const markers = document.querySelectorAll<HTMLElement>('[data-mage-island]');
    announcePageReady(markers.length);
    if (markers.length === 0) {
        return;
    }

    const [{ createApp, createSSRApp }, { default: obsidianI18n }] = await Promise.all([
        import('vue'),
        import('MageObsidian_ModernFrontend::js/i18n'),
    ]);

    hydrateAll(markers, {
        // The component URL is only known at runtime (PHP resolves it per island),
        // so this is an intentionally un-analyzable dynamic import.
        importComponent: (source: string) => import(/* @vite-ignore */ source),
        createApp: (component: unknown, props: Record<string, unknown>, hydrate: boolean) =>
            (hydrate ? createSSRApp : createApp)(component as Parameters<typeof createApp>[0], props),
        clearContainer: (element: HTMLElement) => {
            element.innerHTML = '';
        },
        snapshot: __MAGE_OBSIDIAN_DEV__ ? takeSnapshot : undefined,
        onMounted: __MAGE_OBSIDIAN_DEV__ ? inspect : undefined,
        announce,
        // The engine's minimal `AppLike` only declares `mount`; the real object
        // is a full Vue app, so widen the param to call `use` for plugin wiring.
        configureApp: (app: App) => {
            app.use(obsidianI18n);
            // A store module (imported by this island's component above) publishes
            // the shared Pinia here. Installing it gives stores a proper injection
            // context; islands whose components use no store leave it undefined and
            // never load Pinia. The bootstrap reads the global rather than importing
            // Pinia, so pages without any store ship none.
            const sharedPinia = window.__MAGE_OBSIDIAN_PINIA__;
            if (sharedPinia) {
                app.use(sharedPinia);
            }
        },
        observe: observeOnce,
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    void start();
}
