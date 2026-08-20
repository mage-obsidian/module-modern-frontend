import { describe, it, expect } from "vitest";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { takeSectionPrefetch } from "mage-obsidian/runtime/sectionStoreCore.ts";

const SOURCE = readFileSync(
    fileURLToPath(new URL("../../view/frontend/runtime/section-prefetch.head.js", import.meta.url)),
    "utf8",
);

const URL_CHECKOUT = "/customer/section/load/?sections=obsidian-checkout&force_new_section_timestamp=true";
const PAYLOAD = { "obsidian-checkout": { quote: { subtotal: "$38.00" } } };

function run({ config, response = { ok: true, json: async () => PAYLOAD } } = {}) {
    const calls = [];
    const scope = {
        __MAGE_OBSIDIAN_SECTION_PREFETCH_CONFIG__: config,
        fetch: (url, options) => {
            calls.push({ url, options });

            return response instanceof Error ? Promise.reject(response) : Promise.resolve(response);
        },
    };
    new Function("window", SOURCE)(scope);

    return { scope, calls };
}

describe("section-prefetch.head.js", () => {
    it("puts the section request in flight before any module loads", () => {
        const { calls } = run({
            config: { url: URL_CHECKOUT, sections: ["obsidian-checkout"] },
        });

        expect(calls).toHaveLength(1);
        expect(calls[0].url).toBe(URL_CHECKOUT);
        expect(calls[0].options.credentials).toBe("same-origin");
        expect(calls[0].options.headers["X-Requested-With"]).toBe("XMLHttpRequest");
    });

    it("hands the parsed payload to the section store", async () => {
        const { scope } = run({ config: { url: URL_CHECKOUT, sections: ["obsidian-checkout"] } });

        await expect(takeSectionPrefetch(["obsidian-checkout"], scope)).resolves.toEqual(PAYLOAD);
    });

    it("resolves to null on an error response so the store falls back to the network", async () => {
        const { scope } = run({
            config: { url: URL_CHECKOUT, sections: ["obsidian-checkout"] },
            response: { ok: false, json: async () => PAYLOAD },
        });

        await expect(takeSectionPrefetch(["obsidian-checkout"], scope)).resolves.toBeNull();
    });

    it("swallows a rejected request instead of leaving an unhandled rejection", async () => {
        const { scope } = run({
            config: { url: URL_CHECKOUT, sections: ["obsidian-checkout"] },
            response: new Error("offline"),
        });

        await expect(takeSectionPrefetch(["obsidian-checkout"], scope)).resolves.toBeNull();
    });

    it("does nothing without a config, without sections or without a url", () => {
        expect(run({ config: undefined }).calls).toHaveLength(0);
        expect(run({ config: { url: URL_CHECKOUT, sections: [] } }).calls).toHaveLength(0);
        expect(run({ config: { sections: ["obsidian-checkout"] } }).calls).toHaveLength(0);
        expect(run({ config: { url: "", sections: ["obsidian-checkout"] } }).calls).toHaveLength(0);
    });

    it("leaves no global behind when it does not run", () => {
        const { scope } = run({ config: { url: URL_CHECKOUT, sections: [] } });

        expect(scope.__MAGE_OBSIDIAN_SECTION_PREFETCH__).toBeUndefined();
    });

    it("does not throw where fetch is unavailable", () => {
        const scope = { __MAGE_OBSIDIAN_SECTION_PREFETCH_CONFIG__: { url: URL_CHECKOUT, sections: ["a"] } };

        expect(() => new Function("window", SOURCE)(scope)).not.toThrow();
        expect(scope.__MAGE_OBSIDIAN_SECTION_PREFETCH__).toBeUndefined();
    });
});
