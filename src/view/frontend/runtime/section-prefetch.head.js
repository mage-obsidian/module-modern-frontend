(function (scope) {
    "use strict";

    var config = scope && scope.__MAGE_OBSIDIAN_SECTION_PREFETCH_CONFIG__;
    if (!config || typeof scope.fetch !== "function") {
        return;
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
