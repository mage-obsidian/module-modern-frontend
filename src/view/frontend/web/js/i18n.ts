/**
 * Framework i18n runtime for Vue — the browser side of the translation layer.
 *
 * Provides a Vue plugin (`obsidianI18n`) and a `useTranslate()` composable that
 * translate phrases against Magento's native per-locale `js-translation.json`.
 * The dictionary URL and active locale are published by PHP as the global
 * `window.__MAGE_OBSIDIAN_I18N__`; the dictionary is fetched once (shared across
 * every mounted app) and held in a reactive ref so `$t(...)` re-renders as soon
 * as the dictionary resolves.
 *
 * Mirrors Magento's `$t` so phrases written in `.vue` files (`$t('Add to cart')`)
 * are picked up by `bin/magento mage-obsidian:i18n:collect` and translated with
 * the same CSV / language-pack flow as the rest of the storefront.
 */
import { shallowRef, type App } from 'vue';
import {
    translatePhrase,
    readI18nConfig,
    loadDictionary,
    type Dictionary,
} from 'mage-obsidian/runtime/i18nCore.ts';

const dictionary = shallowRef<Dictionary>({});
let loadStarted = false;

function ensureDictionaryLoading(): void {
    if (loadStarted) {
        return;
    }
    loadStarted = true;
    const { dictionaryUrl } = readI18nConfig();
    loadDictionary(dictionaryUrl).then((loaded) => {
        dictionary.value = loaded;
    });
}

/**
 * Translate a phrase, interpolating `%1`, `%2`, … placeholders. Reads the
 * reactive dictionary so usage inside a render re-runs when it loads.
 */
export function translate(phrase: string, ...args: unknown[]): string {
    return translatePhrase(dictionary.value, phrase, args);
}

/**
 * Composable for `<script setup>` components.
 */
export function useTranslate(): { t: typeof translate; locale: string } {
    ensureDictionaryLoading();
    return { t: translate, locale: readI18nConfig().locale };
}

/**
 * Vue plugin: exposes `$t` in templates and provides the translator for
 * `inject('obsidianTranslate')`.
 */
export const obsidianI18n = {
    install(app: App): void {
        ensureDictionaryLoading();
        app.config.globalProperties.$t = translate;
        app.provide('obsidianTranslate', translate);
    },
};

export default obsidianI18n;
