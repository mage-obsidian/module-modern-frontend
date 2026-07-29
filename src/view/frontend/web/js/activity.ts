import { ref, computed, type ComputedRef } from 'vue';
import { createActivityTracker, matchActivity } from 'mage-obsidian/runtime/activity.ts';
import events from 'MageObsidian_ModernFrontend::js/events';

const GLOBAL_KEY = '__MAGE_OBSIDIAN_ACTIVITY__';

export interface ActivityApi {
    pending(scope: string): number;
    isBusy(scope: string): boolean;
    busy(scope: string): ComputedRef<boolean>;
}

declare global {
    interface Window {
        [GLOBAL_KEY]?: ActivityApi;
    }
}

function create(): ActivityApi {
    const counts = ref<Record<string, number>>({});

    const tracker = createActivityTracker({
        onChange(scope, pending) {
            counts.value = { ...counts.value, [scope]: pending };
        },
        onStuck(scope) {
            console.warn(
                `[MageObsidian] "${scope}" dispatched a _before with no matching _after or ` +
                    '_failed; releasing its loading state.',
            );
        },
    });

    events.onDispatch({
        start(event) {
            const match = matchActivity(event);
            if (match?.phase === 'begin') {
                match.scopes.forEach((scope) => tracker.begin(scope));
            }
        },
        end(event, data) {
            const match = matchActivity(event);
            if (!match) {
                return;
            }
            const cancelled = (data as { cancelled?: boolean }).cancelled === true;
            if (match.phase === 'end' || cancelled) {
                match.scopes.forEach((scope) => tracker.end(scope));
            }
        },
    });

    const pending = (scope: string): number => counts.value[scope] ?? 0;

    return {
        pending,
        isBusy: (scope: string) => pending(scope) > 0,
        busy: (scope: string) => computed(() => pending(scope) > 0),
    };
}

const activity: ActivityApi = (window[GLOBAL_KEY] ??= create());

export function useActivity(): ActivityApi {
    return activity;
}

export default useActivity;
