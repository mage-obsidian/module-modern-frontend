import { describe, it, expect } from "vitest";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import {
    needsHydration,
    sessionInvalidated,
    isSectionStale,
} from "mage-obsidian/runtime/sectionStoreCore.ts";

const SOURCE = readFileSync(
    fileURLToPath(new URL("../../view/frontend/runtime/prepaint.head.js", import.meta.url)),
    "utf8",
);

const STORAGE_KEY = "mage-cache-storage";
const VERSION_KEY = "mage-cache-storage-section-version";
const VERSION_COOKIE = "private_content_version";
const SESSION_COOKIE = "mage-cache-sessid";

const CART_COUNTER = {
    section: "cart",
    field: "summary_count",
    kind: "",
    island: "MageObsidian_Storefront/components/cart/CartCount.js",
    property: "--mo-count-cart",
    attribute: "data-mo-count-cart",
};
const WISHLIST_COUNTER = {
    section: "wishlist",
    field: "saved",
    kind: "size",
    island: "MageObsidian_Storefront/components/wishlist/WishlistCount.js",
    property: "--mo-count-wishlist",
    attribute: "data-mo-count-wishlist",
};
const AUTH_FLAG = { section: "customer", field: "firstname", attribute: "data-mo-auth" };

const config = (over = {}) => ({
    storageKey: STORAGE_KEY,
    versionKey: VERSION_KEY,
    versionCookie: VERSION_COOKIE,
    sessionCookie: SESSION_COOKIE,
    lifetime: 0,
    expirable: [],
    counters: [CART_COUNTER],
    flags: [AUTH_FLAG],
    ...over,
});

function run({ sections, syncedVersion = "v1", cookie = `${VERSION_COOKIE}=v1; ${SESSION_COOKIE}=abc`, ...over }) {
    const attributes = {};
    const properties = {};
    const css = [];
    const storage = {
        [STORAGE_KEY]: sections === undefined ? undefined : JSON.stringify(sections),
        [VERSION_KEY]: syncedVersion,
    };

    const scope = {
        __MAGE_OBSIDIAN_PREPAINT__: config(over),
        localStorage: {
            getItem: (key) => (storage[key] === undefined ? null : storage[key]),
        },
        document: {
            cookie,
            createElement: () => ({
                children: [],
                setAttribute: () => {},
                appendChild(node) {
                    this.children.push(node);
                },
            }),
            createTextNode: (text) => ({ text }),
            head: {
                appendChild: (node) => {
                    css.push(node.children.map((child) => child.text).join(""));
                },
            },
            documentElement: {
                setAttribute: (name, value) => {
                    attributes[name] = value;
                },
                style: {
                    setProperty: (name, value) => {
                        properties[name] = value;
                    },
                },
            },
        },
    };

    new Function("window", SOURCE)(scope);

    return {
        attributes,
        properties,
        css: css.join(""),
        published: Object.keys(attributes).length > 0,
    };
}

describe("pre-paint runtime", () => {
    it("publishes a counter and a flag the stylesheet can render", () => {
        const { attributes, properties } = run({
            sections: { cart: { summary_count: 2 }, customer: { firstname: "Jean" } },
        });

        expect(properties["--mo-count-cart"]).toBe("2");
        expect(attributes["data-mo-count-cart"]).toBe("1");
        expect(attributes["data-mo-auth"]).toBe("1");
    });

    it("says so when the counter is zero, instead of staying silent", () => {
        const { attributes, properties } = run({ sections: { cart: { summary_count: 0 } } });

        expect(properties["--mo-count-cart"]).toBe("0");
        expect(attributes["data-mo-count-cart"]).toBe("0");
    });

    it("marks a guest as known, so the placeholder can commit to one branch", () => {
        const { attributes } = run({ sections: { customer: {} } });

        expect(attributes["data-mo-auth"]).toBe("0");
    });

    it("counts a collection when the section holds one instead of a number", () => {
        const { properties } = run({
            sections: { wishlist: { saved: { 1: {}, 2: {}, 3: {} } }, "compare-products": { items: [{}, {}] } },
            counters: [
                WISHLIST_COUNTER,
                { ...WISHLIST_COUNTER, section: "compare-products", field: "items", property: "--mo-count-compare", attribute: "data-mo-count-compare" },
            ],
        });

        expect(properties["--mo-count-wishlist"]).toBe("3");
        expect(properties["--mo-count-compare"]).toBe("2");
    });

    it("floors a fractional count and refuses a negative or unparsable one", () => {
        expect(run({ sections: { cart: { summary_count: 2.7 } } }).properties["--mo-count-cart"]).toBe("2");
        expect(run({ sections: { cart: { summary_count: -5 } } }).properties["--mo-count-cart"]).toBe("0");
        expect(run({ sections: { cart: { summary_count: "many" } } }).properties["--mo-count-cart"]).toBe("0");
    });

    it("leaves a section it was not told about alone", () => {
        const { attributes } = run({ sections: { customer: { firstname: "Jean" } } });

        expect(attributes["data-mo-count-cart"]).toBeUndefined();
        expect(attributes["data-mo-auth"]).toBe("1");
    });
});

describe("pre-paint freshness, against the section store's own rules", () => {
    const cases = [
        { name: "everything in sync", sections: { cart: { summary_count: 2 } }, synced: "v1", current: "v1", session: "abc" },
        { name: "the version cookie moved", sections: { cart: { summary_count: 2 } }, synced: "v1", current: "v2", session: "abc" },
        { name: "nothing cached yet", sections: {}, synced: "", current: "v1", session: "abc" },
        { name: "the session marker is gone", sections: { cart: { summary_count: 2 } }, synced: "v1", current: "v1", session: "" },
        { name: "no version cookie at all", sections: { cart: { summary_count: 2 } }, synced: "v1", current: "", session: "abc" },
    ];

    for (const item of cases) {
        it(`agrees with needsHydration/sessionInvalidated when ${item.name}`, () => {
            const cookie = [
                item.current ? `${VERSION_COOKIE}=${item.current}` : "",
                item.session ? `${SESSION_COOKIE}=${item.session}` : "",
            ]
                .filter(Boolean)
                .join("; ");

            const trustedByStore =
                !needsHydration(item.sections, item.synced, item.current) &&
                !sessionInvalidated(cookie, SESSION_COOKIE);

            expect(run({ sections: item.sections, syncedVersion: item.synced, cookie }).published).toBe(
                trustedByStore,
            );
        });
    }

    it("agrees with isSectionStale on a section that aged out", () => {
        const lifetime = 3600;
        const now = Math.floor(Date.now() / 1000);
        const expired = { data_id: now - lifetime - 1, summary_count: 2 };
        const alive = { data_id: now, summary_count: 2 };

        expect(isSectionStale(expired, lifetime, now)).toBe(true);
        expect(isSectionStale(alive, lifetime, now)).toBe(false);

        const dead = run({ sections: { cart: expired }, lifetime, expirable: ["cart"] });
        const live = run({ sections: { cart: alive }, lifetime, expirable: ["cart"] });

        expect(dead.properties["--mo-count-cart"]).toBeUndefined();
        expect(live.properties["--mo-count-cart"]).toBe("2");
    });

    it("only ages out the sections declared expirable", () => {
        const lifetime = 3600;
        const stale = { data_id: Math.floor(Date.now() / 1000) - lifetime - 1, firstname: "Jean" };

        const { attributes } = run({ sections: { customer: stale }, lifetime, expirable: ["cart"] });

        expect(attributes["data-mo-auth"]).toBe("1");
    });
});

describe("pre-paint under a hostile browser", () => {
    it("does nothing when localStorage throws", () => {
        const scope = {
            __MAGE_OBSIDIAN_PREPAINT__: config(),
            localStorage: {
                getItem: () => {
                    throw new Error("blocked");
                },
            },
            document: { cookie: "", documentElement: { setAttribute: () => {}, style: { setProperty: () => {} } } },
        };

        expect(() => new Function("window", SOURCE)(scope)).not.toThrow();
    });

    it("does nothing on corrupt or absent storage", () => {
        expect(run({ sections: undefined }).published).toBe(false);
        expect(run({ sections: [] }).published).toBe(false);
    });

    it("does nothing when the page published no config", () => {
        const scope = { document: { documentElement: {} } };

        expect(() => new Function("window", SOURCE)(scope)).not.toThrow();
    });
});

describe("pre-paint stylesheet", () => {
    it("draws the badge on the island itself, so a counter needs no markup of its own", () => {
        const { css } = run({ sections: { cart: { summary_count: 2 } } });

        expect(css).toContain('[data-mage-island]:not([data-mage-island-ready])[data-component$="MageObsidian_Storefront/components/cart/CartCount.js"]::after');
        expect(css).toContain('content:"2"');
        expect(css).not.toContain("background:");
    });

    it("emits a rule per declared counter without either being named in a stylesheet", () => {
        const { css } = run({
            sections: { cart: { summary_count: 1 }, wishlist: { saved: { a: {} } } },
            counters: [CART_COUNTER, WISHLIST_COUNTER],
        });

        expect(css).toContain("components/cart/CartCount.js");
        expect(css).toContain("components/wishlist/WishlistCount.js");
        expect(css).not.toContain("[data-mage-island][data-component");
    });

    it("draws nothing for an empty counter", () => {
        expect(run({ sections: { cart: { summary_count: 0 } } }).css).toBe("");
    });
});
