/**
 * `useCustomerData` — the Magento binding of the generic section store.
 *
 * It mirrors Magento's private content (the `customer-data` sections: cart,
 * customer, wishlist, …) into a reactive Pinia store shared across islands. All
 * the mechanism lives in the domain-agnostic `createSectionStore` factory; this
 * file only supplies the Magento specifics: the `/customer/section/load/`
 * endpoint, the `private_content_version` cookie and the `mage-cache-storage`
 * keys. The name "customer data" belongs here — to the Magento integration —
 * not to the generic store.
 *
 * Read-mostly by design: Magento's native `customer-data.js`, when present,
 * remains the owner of the canonical store and the section-load endpoint, so
 * this never breaks Full Page Cache. In this stack (no native customer-data),
 * the binding owns persistence itself — the factory handles that transparently.
 *
 * Opt-in: importing this module loads Pinia and activates the shared instance;
 * components that never import it pay nothing. Requires `pinia` in the theme's
 * `exposeNpmPackages` (the build fails loudly otherwise).
 */
import { createSectionStore } from 'MageObsidian_ModernFrontend::js/section-store';
import { readSectionRuntimeConfig } from 'mage-obsidian/runtime/sectionStoreCore.ts';

// Lifetime + expirable sections published by PHP (SectionDataConfig). They feed
// the time-based backstop so a section whose server data changed without a POST
// — e.g. the cart after its quote expired with the PHP session — still ages out
// and reloads, instead of the badge showing a stale count forever.
const runtime = readSectionRuntimeConfig();

export const useCustomerData = createSectionStore({
    id: 'mageObsidianCustomerData',
    endpoint: 'customer/section/load/',
    storageKey: 'mage-cache-storage',
    // Our own marker for the last private-content version we fully synced for.
    // We can't reuse Magento's native `section_data_ids` bookkeeping (its
    // customer-data is not loaded in this stack), so the binding owns this.
    versionKey: 'mage-cache-storage-section-version',
    versionCookie: 'private_content_version',
    lifetimeSeconds: runtime.lifetimeSeconds,
    expirableSections: runtime.expirableSections,
});

export default useCustomerData;
