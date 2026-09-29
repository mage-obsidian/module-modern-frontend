// This file is part of the MageObsidian - ModernFrontend project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
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
