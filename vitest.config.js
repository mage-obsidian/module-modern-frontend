import { defineConfig } from "vitest/config";
import { fileURLToPath } from "node:url";

export default defineConfig({
    resolve: {
        alias: {
            "mage-obsidian/runtime": fileURLToPath(
                new URL("../js-package-utils/src/runtime", import.meta.url),
            ),
        },
    },
    test: {
        environment: "node",
        include: ["src/Test/Js/**/*.test.js"],
    },
});
