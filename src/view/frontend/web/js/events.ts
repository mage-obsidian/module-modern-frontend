/**
 * The storefront's shared event bus — Magento's observers, on the front end.
 *
 * One instance per page, published on `window` so code that is not part of the
 * bundle (a tag manager, a merchant's inline snippet) can subscribe without an
 * import. Every dispatch is also mirrored as a `CustomEvent` named
 * `obsidian:<event>` on `window`, which is the only thing a third-party script
 * needs to listen for — but a DOM listener cannot amend the data, so a module
 * that wants to change what happens registers a real observer instead.
 */
import { EventManager } from 'mage-obsidian/runtime/eventManager.ts';

const GLOBAL_KEY = '__MAGE_OBSIDIAN_EVENTS__';
const DOM_PREFIX = 'obsidian:';

declare global {
    interface Window {
        [GLOBAL_KEY]?: EventManager;
    }
}

function create(): EventManager {
    const manager = new EventManager();
    const dispatch = manager.dispatch.bind(manager);

    manager.dispatch = async <T extends object>(event: string, data: T): Promise<T> => {
        const result = await dispatch(event, data);
        window.dispatchEvent(new CustomEvent(`${DOM_PREFIX}${event}`, { detail: result }));
        return result;
    };

    return manager;
}

export const events: EventManager = (window[GLOBAL_KEY] ??= create());

export default events;
