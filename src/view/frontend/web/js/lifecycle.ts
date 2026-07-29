import events from 'MageObsidian_ModernFrontend::js/events';
import { LifecycleEvent, type PageEvent } from 'mage-obsidian/runtime/lifecycleEvents.ts';

const HIDDEN = 'hidden';

export function announcePageReady(islands: number): void {
    void events.dispatch(
        LifecycleEvent.PageReady,
        { url: window.location.href, islands } satisfies PageEvent,
        { sticky: true },
    );
}

export function bindPageLifecycle(win: Window & typeof globalThis = window): () => void {
    const onVisibility = (): void => {
        void events.dispatch(
            win.document.visibilityState === HIDDEN
                ? LifecycleEvent.PageHidden
                : LifecycleEvent.PageVisible,
            { url: win.location.href } satisfies PageEvent,
        );
    };

    const onPageHide = (event: Event): void => {
        void events.dispatch(LifecycleEvent.PageLeave, {
            url: win.location.href,
            persisted: (event as PageTransitionEvent).persisted === true,
        } satisfies PageEvent);
    };

    win.document.addEventListener('visibilitychange', onVisibility);
    win.addEventListener('pagehide', onPageHide);

    return () => {
        win.document.removeEventListener('visibilitychange', onVisibility);
        win.removeEventListener('pagehide', onPageHide);
    };
}
