/**
 * `createSectionStore` — a generic factory that mirrors a set of server-pushed,
 * versioned "sections" into a reactive Pinia store backed by localStorage.
 *
 * This is the domain-agnostic mechanism: parse/select/merge sections, persist
 * them, and (re)load from a server endpoint, re-hydrating when a version cookie
 * moves. It knows nothing about WHICH endpoint/cookie/storage key an integration
 * uses — a binding passes those in. The Magento customer-data adapter is one
 * such binding (see `customer-data.ts`), but anyone can create their own.
 *
 * Read-mostly by design: when a native producer already owns the canonical
 * localStorage store and broadcasts updates, this only mirrors it (Full Page
 * Cache stays safe — section data lives in localStorage, refreshed from a
 * never-cached endpoint, never baked into cached HTML). When there is no native
 * producer, this store owns persistence itself.
 *
 * Opt-in: importing a store built with this factory loads Pinia and activates
 * the shared instance; components that never import it pay nothing.
 */
import { ref } from 'vue';
import { defineStore } from 'pinia';
import {
    parseSectionStorage,
    selectSection,
    mergeSections,
    buildSectionLoadUrl,
    readCookie,
    needsHydration,
    sessionInvalidated,
    expiredSectionNames,
    type SectionData,
    type SectionMap,
} from 'mage-obsidian/runtime/sectionStoreCore.ts';
import { ensureSharedPinia } from 'MageObsidian_ModernFrontend::js/store';

export interface SectionStoreConfig {
    /** Unique Pinia store id. */
    id: string;
    /** Section-load endpoint (e.g. 'customer/section/load/'). */
    endpoint: string;
    /** localStorage key holding the sections JSON. */
    storageKey: string;
    /** localStorage key holding the last synced version. */
    versionKey: string;
    /** Cookie name advertising the current version. */
    versionCookie: string;
    /** Space-separated jQuery document events that signal an update. */
    reloadEvents?: string;
    /** Session marker cookie (e.g. 'mage-cache-sessid'); its absence invalidates
     *  all sections on login/logout. Omit to disable. */
    sessionCookie?: string;
    /** Per-section freshness window; 0 disables the time-based backstop. */
    lifetimeSeconds?: number;
    /** Sections eligible for time-based expiry (the rest never age out). */
    expirableSections?: string[];
}

// Minimal shape of the global jQuery used only for Magento's document events;
// the legacy library is not a typed dependency of this stack.
type JQueryLike = (target: unknown) => { on(events: string, handler: () => void): void };

/**
 * Build a reactive, server-synced section store.
 */
export function createSectionStore(config: SectionStoreConfig) {
    const {
        id,
        endpoint,
        storageKey,
        versionKey,
        versionCookie,
        reloadEvents = 'customer-data-reload customer-data-invalidate',
        lifetimeSeconds = 0,
        expirableSections = [],
        sessionCookie = '',
    } = config;

    // Activate the shared Pinia before any component calls the store.
    ensureSharedPinia();

    function readStorage(): SectionMap {
        if (typeof localStorage === 'undefined') {
            return {};
        }
        return parseSectionStorage(localStorage.getItem(storageKey));
    }

    /** The current version the producer advertises via cookie. */
    function cookieVersion(): string {
        return readCookie(typeof document !== 'undefined' ? document.cookie : '', versionCookie);
    }

    // Session-scoped; Magento deletes it on login/logout, and its absence next
    // load is what triggers invalidation.
    function armSessionCookie(): void {
        if (!sessionCookie || typeof document === 'undefined') {
            return;
        }
        const secure = typeof location !== 'undefined' && location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${sessionCookie}=1; path=/; SameSite=Lax${secure}`;
    }

    function readSyncedVersion(): string {
        if (typeof localStorage === 'undefined') {
            return '';
        }
        return localStorage.getItem(versionKey) ?? '';
    }

    /**
     * Persist the mirrored sections so the next page load starts warm. Only a
     * full reload (all sections) stamps the version as fully-synced; a partial
     * reload leaves the marker behind so other invalidated sections still
     * re-hydrate next load.
     */
    function writeStorage(sections: SectionMap, syncedVersion?: string): void {
        if (typeof localStorage === 'undefined') {
            return;
        }
        try {
            localStorage.setItem(storageKey, JSON.stringify(sections));
            if (syncedVersion !== undefined) {
                localStorage.setItem(versionKey, syncedVersion);
            }
        } catch {
            // Storage may be full or unavailable (private mode); the in-memory
            // snapshot still works for this page.
        }
    }

    return defineStore(id, () => {
        const sections = ref<SectionMap>(readStorage());
        let subscribed = false;

        /** Re-read the canonical store after the producer updates it. */
        function sync(): void {
            sections.value = readStorage();
        }

        /** A single section (e.g. 'cart', 'customer', 'wishlist'), or null. */
        function section(name: string): SectionData | null {
            return selectSection(sections.value, name);
        }

        /**
         * Explicitly (re)load sections from the never-cached endpoint and merge
         * the result. An empty `names` reloads all sections.
         */
        async function reload(names: string[] = [], options: { force?: boolean } = {}): Promise<void> {
            const url = buildSectionLoadUrl(endpoint, names, { forceNewTimestamp: options.force ?? true });
            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    return;
                }
                sections.value = mergeSections(sections.value, await response.json());
                writeStorage(sections.value, names.length === 0 ? cookieVersion() : undefined);
            } catch {
                // Keep the current snapshot; section data is never page-critical.
            }
        }

        /**
         * Hydrate from the server on first use when the cache is empty or the
         * version has moved. A no-op on steady-state loads, so warm pages stay
         * request-free. As a backstop, expirable sections that have aged past
         * their lifetime are reloaded too — this catches server-side changes the
         * version cookie misses (it only moves on POST), e.g. a cart section that
         * outlived its quote after the PHP session expired.
         */
        function hydrate(): void {
            // Session flipped (marker deleted on login/logout): drop the stale
            // snapshot and reload fresh — the version cookie misses this.
            if (sessionInvalidated(typeof document !== 'undefined' ? document.cookie : '', sessionCookie)) {
                sections.value = {};
                writeStorage({}, '');
                armSessionCookie();
                reload([]);
                return;
            }
            if (needsHydration(sections.value, readSyncedVersion(), cookieVersion())) {
                reload([]);
                return;
            }
            const expired = expiredSectionNames(
                sections.value,
                lifetimeSeconds,
                expirableSections,
                Math.floor(Date.now() / 1000),
            );
            if (expired.length) {
                reload(expired);
            }
        }

        /**
         * Subscribe once to the producer's update broadcasts. Magento dispatches
         * jQuery document events (not native DOM events), so we hook them through
         * the global jQuery when present; otherwise we fall back to cross-tab
         * `storage` events.
         */
        function subscribe(): void {
            if (subscribed || typeof window === 'undefined') {
                return;
            }
            subscribed = true;

            const jq = (window as unknown as { jQuery?: JQueryLike }).jQuery;
            if (typeof jq === 'function') {
                jq(window.document).on(reloadEvents, sync);
                return;
            }

            window.addEventListener('storage', (event) => {
                if (event.key === storageKey) {
                    sync();
                }
            });
        }

        /**
         * Hydrate off the critical path. Section data (cart/customer/wishlist
         * counts) is never needed for first paint, so deferring the cold-load
         * fetch to browser idle keeps the `section/load` request out of the
         * initial critical chain; warm loads are a no-op regardless. Falls back to
         * a synchronous hydrate without a window (SSR/tests).
         */
        function scheduleHydrate(): void {
            if (typeof window === 'undefined') {
                hydrate();
                return;
            }
            const idle = (window as Window & {
                requestIdleCallback?: (callback: () => void) => number;
            }).requestIdleCallback;
            if (typeof idle === 'function') {
                idle(() => hydrate());
            } else {
                window.setTimeout(hydrate, 0);
            }
        }

        subscribe();
        scheduleHydrate();

        return { sections, section, sync, reload };
    });
}

export default createSectionStore;
