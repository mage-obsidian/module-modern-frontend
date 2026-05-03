/**
 * `useCustomerData` — a Pinia store that mirrors Magento's private content
 * (the `customer-data` sections: cart, customer, wishlist, …) into reactive
 * state shared across islands.
 *
 * Read-mostly by design: Magento's native `customer-data.js` remains the owner
 * of the canonical store (`localStorage['mage-cache-storage']`) and the
 * `/customer/section/load/` endpoint, so this never breaks Full Page Cache. We
 * read that cache, re-sync when Magento broadcasts an update (its jQuery
 * document events), and expose `reload()` for an explicit refresh.
 *
 * Opt-in: importing this module loads Pinia and activates the shared instance;
 * components that never import it pay nothing. Requires `pinia` in the theme's
 * `exposeNpmPackages` (the build fails loudly otherwise).
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
} from 'mage-obsidian/runtime/customerDataCore.ts';
import { ensureSharedPinia } from './store.js';

const STORAGE_KEY = 'mage-cache-storage';
// Our own marker for the last private-content version we fully synced for. We
// can't reuse Magento's native `section_data_ids` bookkeeping (its customer-data
// is not loaded in this stack), so the bridge owns this.
const VERSION_KEY = 'mage-cache-storage-section-version';
const VERSION_COOKIE = 'private_content_version';

// Activate the shared Pinia before any component calls useCustomerData().
ensureSharedPinia();

function readStorage() {
    if (typeof localStorage === 'undefined') {
        return {};
    }
    return parseSectionStorage(localStorage.getItem(STORAGE_KEY));
}

/** The current private-content version Magento advertises via cookie. */
function cookieVersion() {
    return readCookie(typeof document !== 'undefined' ? document.cookie : '', VERSION_COOKIE);
}

function readSyncedVersion() {
    if (typeof localStorage === 'undefined') {
        return '';
    }
    return localStorage.getItem(VERSION_KEY) ?? '';
}

/**
 * Write the mirrored sections back to `mage-cache-storage` so the next page load
 * starts warm (and stays FPC-safe — the data lives in localStorage, refreshed
 * from the never-cached section endpoint, never baked into cached HTML). Since
 * this stack has no native customer-data, the bridge is the store's owner.
 */
function writeStorage(sections, syncedVersion) {
    if (typeof localStorage === 'undefined') {
        return;
    }
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(sections));
        if (syncedVersion !== undefined) {
            localStorage.setItem(VERSION_KEY, syncedVersion);
        }
    } catch {
        // Storage may be full or unavailable (private mode); the in-memory
        // snapshot still works for this page.
    }
}

export const useCustomerData = defineStore('mageObsidianCustomerData', () => {
    const sections = ref(readStorage());
    let subscribed = false;

    /** Re-read the canonical store after Magento updates it. */
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
     * Explicitly (re)load sections from Magento's never-cached endpoint and
     * merge the result. Magento also persists them to localStorage, so its
     * other components stay in sync.
     *
     * @param {Array<string>} [names] Empty → all sections.
     * @param {{ force?: boolean }} [options]
     * @returns {Promise<void>}
     */
    async function reload(names = [], options = {}) {
        const url = buildSectionLoadUrl(names, { forceNewTimestamp: options.force ?? true });
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                return;
            }
            sections.value = mergeSections(sections.value, await response.json());
            // Persist for the next page load. Only a full reload (all sections)
            // stamps the version as fully-synced; a partial reload leaves the
            // marker behind so other invalidated sections still re-hydrate next
            // load.
            writeStorage(sections.value, names.length === 0 ? cookieVersion() : undefined);
        } catch {
            // Keep the current snapshot; structured data is never page-critical.
        }
    }

    /**
     * Hydrate from the server on first use when the cache is empty or the
     * private-content version has moved (Magento bumped it after a cart/customer
     * change). A no-op on steady-state loads, so warm pages stay request-free.
     */
    function hydrate() {
        if (needsHydration(sections.value, readSyncedVersion(), cookieVersion())) {
            reload([]);
        }
    }

    /**
     * Subscribe once to Magento's update broadcasts. Magento dispatches jQuery
     * document events (not native DOM events), so we hook them through the
     * global jQuery when present; otherwise we fall back to cross-tab `storage`
     * events.
     */
    function subscribe() {
        if (subscribed || typeof window === 'undefined') {
            return;
        }
        subscribed = true;

        const jq = window.jQuery;
        if (typeof jq === 'function') {
            jq(window.document).on('customer-data-reload customer-data-invalidate', sync);
            return;
        }

        window.addEventListener('storage', (event) => {
            if (event.key === STORAGE_KEY) {
                sync();
            }
        });
    }

    subscribe();
    hydrate();

    return { sections, section, sync, reload };
});

export default useCustomerData;
