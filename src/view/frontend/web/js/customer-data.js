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
} from 'mage-obsidian/runtime/customerDataCore.ts';
import { ensureSharedPinia } from './store.js';

const STORAGE_KEY = 'mage-cache-storage';

// Activate the shared Pinia before any component calls useCustomerData().
ensureSharedPinia();

function readStorage() {
    if (typeof localStorage === 'undefined') {
        return {};
    }
    return parseSectionStorage(localStorage.getItem(STORAGE_KEY));
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
        } catch {
            // Keep the current snapshot; structured data is never page-critical.
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

    return { sections, section, sync, reload };
});

export default useCustomerData;
