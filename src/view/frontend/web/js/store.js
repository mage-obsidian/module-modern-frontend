/**
 * Shared Pinia instance for the Vue/ESM layer — the opt-in enabler for state
 * shared across islands.
 *
 * Pinia is intentionally NOT installed on every island in the bootstrap: a
 * component that needs no global state must not pull Pinia in. Instead, a store
 * module calls `ensureSharedPinia()` on import, which creates one page-wide
 * instance and marks it active. Because `useStore()` resolves against the active
 * Pinia when an app didn't `app.use(pinia)`, any component that imports a store
 * gets shared state — and any component that imports none never loads Pinia at
 * all (native ESM, pay-per-use).
 *
 * To author your own cross-island store, call `ensureSharedPinia()` at the top
 * of your store module before `defineStore`.
 */
import { createPinia, setActivePinia, getActivePinia } from 'pinia';

let shared;

/**
 * Return the page-wide shared Pinia instance, creating and activating it on
 * first use (reusing an already-active one if present).
 *
 * @returns {import('pinia').Pinia}
 */
export function ensureSharedPinia() {
    if (!shared) {
        shared = getActivePinia() ?? createPinia();
        setActivePinia(shared);
    }
    return shared;
}

export default ensureSharedPinia;
