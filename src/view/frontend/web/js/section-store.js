/**
 * `createSectionStore` — a generic factory that mirrors a set of server-pushed,
 * versioned "sections" into a reactive Pinia store backed by localStorage.
 *
 * This is the domain-agnostic mechanism: parse/select/merge sections, persist
 * them, and (re)load from a server endpoint, re-hydrating when a version cookie
 * moves. It knows nothing about WHICH endpoint/cookie/storage key an integration
 * uses — a binding passes those in. The Magento customer-data adapter is one
 * such binding (see `customer-data.js`), but anyone can create their own.
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
    expiredSectionNames,
} from 'mage-obsidian/runtime/sectionStoreCore.ts';
import { ensureSharedPinia } from 'MageObsidian_ModernFrontend::js/store';

/**
 * @typedef {object} SectionStoreConfig
 * @property {string} id              Unique Pinia store id.
 * @property {string} endpoint        Section-load endpoint (e.g. 'customer/section/load/').
 * @property {string} storageKey      localStorage key holding the sections JSON.
 * @property {string} versionKey      localStorage key holding the last synced version.
 * @property {string} versionCookie   Cookie name advertising the current version.
 * @property {string} [reloadEvents]  Space-separated jQuery document events that signal an update.
 * @property {number} [lifetimeSeconds]   Per-section freshness window; 0 disables the time-based backstop.
 * @property {string[]} [expirableSections] Sections eligible for time-based expiry (the rest never age out).
 */

/**
 * Build a reactive, server-synced section store.
 *
 * @param {SectionStoreConfig} config
 * @returns {import('pinia').StoreDefinition}
 */
export function createSectionStore(config) {
    const {
        id,
        endpoint,
        storageKey,
        versionKey,
        versionCookie,
        reloadEvents = 'customer-data-reload customer-data-invalidate',
        lifetimeSeconds = 0,
        expirableSections = [],
    } = config;

    // Activate the shared Pinia before any component calls the store.
    ensureSharedPinia();

    function readStorage() {
        if (typeof localStorage === 'undefined') {
            return {};
        }
        return parseSectionStorage(localStorage.getItem(storageKey));
    }

    /** The current version the producer advertises via cookie. */
    function cookieVersion() {
        return readCookie(typeof document !== 'undefined' ? document.cookie : '', versionCookie);
    }

    function readSyncedVersion() {
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
    function writeStorage(sections, syncedVersion) {
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
        const sections = ref(readStorage());
        let subscribed = false;

        /** Re-read the canonical store after the producer updates it. */
        function sync() {
            sections.value = readStorage();
        }

        /**
         * A single section (e.g. 'cart', 'customer', 'wishlist'), or null.
         *
         * @param {string} name
         * @returns {Record<string, unknown> | null}
         */
        function section(name) {
            return selectSection(sections.value, name);
        }

        /**
         * Explicitly (re)load sections from the never-cached endpoint and merge
         * the result.
         *
         * @param {Array<string>} [names] Empty → all sections.
         * @param {{ force?: boolean }} [options]
         * @returns {Promise<void>}
         */
        async function reload(names = [], options = {}) {
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
        function hydrate() {
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
        function subscribe() {
            if (subscribed || typeof window === 'undefined') {
                return;
            }
            subscribed = true;

            const jq = window.jQuery;
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

        subscribe();
        hydrate();

        return { sections, section, sync, reload };
    });
}

export default createSectionStore;
