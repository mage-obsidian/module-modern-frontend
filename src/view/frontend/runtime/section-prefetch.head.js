// This file is part of the MageObsidian - ModernFrontend project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
(function (scope) {
    "use strict";

    var doc = scope && scope.document;
    var config = pageConfig("section-prefetch-config");
    if (!config || typeof scope.fetch !== "function") {
        return;
    }

    function pageConfig(name) {
        var block =
            doc && typeof doc.querySelector === "function"
                ? doc.querySelector('script[type="application/json"][data-mage-obsidian-config="' + name + '"]')
                : null;
        try {
            return block ? JSON.parse(block.textContent) : null;
        } catch (e) {
            return null;
        }
    }

    var sections = config.sections instanceof Array ? config.sections : [];
    if (!sections.length || typeof config.url !== "string" || config.url === "") {
        return;
    }

    scope.__MAGE_OBSIDIAN_SECTION_PREFETCH__ = {
        sections: sections,
        data: scope
            .fetch(config.url, {
                credentials: "same-origin",
                headers: { "X-Requested-With": "XMLHttpRequest" },
            })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .catch(function () {
                return null;
            }),
    };
})(typeof window !== "undefined" ? window : undefined);
