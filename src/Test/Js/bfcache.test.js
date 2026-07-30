import { describe, it, expect, vi } from "vitest";
import {
    bindRehydrateOnRestore,
    bindRehydrateOnActivate,
    PAGE_RESTORE_EVENT,
    PRERENDER_ACTIVATE_EVENT,
} from "../../view/frontend/web/js/bfcache.ts";

const fakeTarget = () => {
    const listeners = new Map();

    return {
        addEventListener: (type, listener) => listeners.set(type, listener),
        removeEventListener: (type, listener) => {
            if (listeners.get(type) === listener) {
                listeners.delete(type);
            }
        },
        emit: (type, event) => listeners.get(type)?.(event),
        bound: (type) => listeners.has(type),
    };
};

describe("bindRehydrateOnRestore", () => {
    it("rehydrates when the page comes back from the bfcache", () => {
        const target = fakeTarget();
        const rehydrate = vi.fn();
        bindRehydrateOnRestore(target, rehydrate);

        target.emit(PAGE_RESTORE_EVENT, { persisted: true });

        expect(rehydrate).toHaveBeenCalledTimes(1);
    });

    it("stays out of the way on an ordinary load", () => {
        const target = fakeTarget();
        const rehydrate = vi.fn();
        bindRehydrateOnRestore(target, rehydrate);

        target.emit(PAGE_RESTORE_EVENT, { persisted: false });

        expect(rehydrate).not.toHaveBeenCalled();
    });

    it("rehydrates on every restore, not just the first", () => {
        const target = fakeTarget();
        const rehydrate = vi.fn();
        bindRehydrateOnRestore(target, rehydrate);

        target.emit(PAGE_RESTORE_EVENT, { persisted: true });
        target.emit(PAGE_RESTORE_EVENT, { persisted: true });

        expect(rehydrate).toHaveBeenCalledTimes(2);
    });

    it("unbinds on teardown", () => {
        const target = fakeTarget();
        const rehydrate = vi.fn();

        const unbind = bindRehydrateOnRestore(target, rehydrate);
        unbind();

        expect(target.bound(PAGE_RESTORE_EVENT)).toBe(false);
        target.emit(PAGE_RESTORE_EVENT, { persisted: true });
        expect(rehydrate).not.toHaveBeenCalled();
    });
});

describe("bindRehydrateOnActivate", () => {
    it("rehydrates when a prerendered document is activated", () => {
        const doc = { ...fakeTarget(), prerendering: true };
        const rehydrate = vi.fn();
        bindRehydrateOnActivate(doc, rehydrate);

        doc.emit(PRERENDER_ACTIVATE_EVENT, {});

        expect(rehydrate).toHaveBeenCalledTimes(1);
    });

    it("binds nothing on a document that was never prerendered", () => {
        const doc = { ...fakeTarget(), prerendering: false };
        const rehydrate = vi.fn();

        bindRehydrateOnActivate(doc, rehydrate);

        expect(doc.bound(PRERENDER_ACTIVATE_EVENT)).toBe(false);
    });

    it("binds nothing where prerendering is unsupported", () => {
        const doc = fakeTarget();
        const rehydrate = vi.fn();

        bindRehydrateOnActivate(doc, rehydrate);

        expect(doc.bound(PRERENDER_ACTIVATE_EVENT)).toBe(false);
    });

    it("unbinds on teardown", () => {
        const doc = { ...fakeTarget(), prerendering: true };
        const rehydrate = vi.fn();

        bindRehydrateOnActivate(doc, rehydrate)();

        expect(doc.bound(PRERENDER_ACTIVATE_EVENT)).toBe(false);
    });
});

describe("bindRehydrateOnActivate", () => {
    it("rehydrates when a prerendered page is activated", () => {
        const doc = { ...fakeTarget(), prerendering: true };
        const rehydrate = vi.fn();
        bindRehydrateOnActivate(doc, rehydrate);

        doc.emit(PRERENDER_ACTIVATE_EVENT, {});

        expect(rehydrate).toHaveBeenCalledTimes(1);
    });

    it("binds nothing on a page that was never prerendered", () => {
        const doc = { ...fakeTarget(), prerendering: false };
        const rehydrate = vi.fn();

        bindRehydrateOnActivate(doc, rehydrate);

        expect(doc.bound(PRERENDER_ACTIVATE_EVENT)).toBe(false);
    });

    it("treats a browser without prerendering support as not prerendering", () => {
        const doc = fakeTarget();
        const rehydrate = vi.fn();

        bindRehydrateOnActivate(doc, rehydrate);

        expect(doc.bound(PRERENDER_ACTIVATE_EVENT)).toBe(false);
    });

    it("unbinds on teardown", () => {
        const doc = { ...fakeTarget(), prerendering: true };
        const rehydrate = vi.fn();

        bindRehydrateOnActivate(doc, rehydrate)();

        expect(doc.bound(PRERENDER_ACTIVATE_EVENT)).toBe(false);
    });
});
