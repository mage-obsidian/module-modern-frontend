import { EventManager } from 'mage-obsidian/runtime/eventManager.ts';
import 'mage-obsidian/runtime/lifecycleEvents.ts';

const GLOBAL_KEY = '__MAGE_OBSIDIAN_EVENTS__';
const DOM_PREFIX = 'obsidian:';

declare const __MAGE_OBSIDIAN_DEV__: boolean;

declare global {
    interface Window {
        [GLOBAL_KEY]?: EventManager;
    }
}

function create(): EventManager {
    const manager = new EventManager({ debug: __MAGE_OBSIDIAN_DEV__ });

    manager.onDispatch({
        end(event, data, options) {
            if (options.mirror === false) {
                return;
            }
            window.dispatchEvent(new CustomEvent(`${DOM_PREFIX}${event}`, { detail: data }));
        },
    });

    return manager;
}

export const events: EventManager = (window[GLOBAL_KEY] ??= create());

export default events;
