/**
 * A frozen page runs no JavaScript, so every update it slept through is gone by
 * the time it comes back and nothing replays it.
 */

export const PAGE_RESTORE_EVENT = "pageshow";

export interface RestoreTarget {
    addEventListener(
        type: string,
        listener: (event: Event) => void,
        options?: AddEventListenerOptions,
    ): void;
    removeEventListener(type: string, listener: (event: Event) => void): void;
}

export function bindRehydrateOnRestore(
    target: RestoreTarget,
    rehydrate: () => void,
): () => void {
    const onPageShow = (event: Event): void => {
        if ((event as PageTransitionEvent).persisted) {
            rehydrate();
        }
    };

    target.addEventListener(PAGE_RESTORE_EVENT, onPageShow, { passive: true });

    return () => {
        target.removeEventListener(PAGE_RESTORE_EVENT, onPageShow);
    };
}

export const PRERENDER_ACTIVATE_EVENT = "prerenderingchange";

export interface PrerenderDocument extends RestoreTarget {
    prerendering?: boolean;
}

/**
 * A prerendered page is built minutes before anyone opens it, so whatever it
 * fetched on the way is already history by the time it is activated.
 */
export function bindRehydrateOnActivate(
    doc: PrerenderDocument,
    rehydrate: () => void,
): () => void {
    if (doc.prerendering !== true) {
        return () => {};
    }

    const onActivate = (): void => rehydrate();
    doc.addEventListener(PRERENDER_ACTIVATE_EVENT, onActivate, { once: true });

    return () => {
        doc.removeEventListener(PRERENDER_ACTIVATE_EVENT, onActivate);
    };
}

export default bindRehydrateOnRestore;
