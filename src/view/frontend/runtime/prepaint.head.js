(function (scope) {
    "use strict";

    var config = scope && scope.__MAGE_OBSIDIAN_PREPAINT__;
    var doc = scope && scope.document;
    var root = doc && doc.documentElement;
    if (!config || !root) {
        return;
    }

    function cookie(name) {
        if (!name) {
            return "";
        }
        var parts = String(doc.cookie || "").split(";");
        for (var i = 0; i < parts.length; i++) {
            var at = parts[i].indexOf("=");
            if (at === -1) {
                continue;
            }
            if (parts[i].slice(0, at).trim() !== name) {
                continue;
            }
            var raw = parts[i].slice(at + 1).trim();
            try {
                return decodeURIComponent(raw);
            } catch (e) {
                return raw;
            }
        }
        return "";
    }

    function stored(key) {
        try {
            return scope.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function parse(raw) {
        if (typeof raw !== "string" || raw === "") {
            return null;
        }
        try {
            var value = JSON.parse(raw);
            return value && typeof value === "object" && !(value instanceof Array) ? value : null;
        } catch (e) {
            return null;
        }
    }

    var sections = parse(stored(config.storageKey));
    if (!sections) {
        return;
    }

    var version = cookie(config.versionCookie);
    if (version !== "" && version !== (stored(config.versionKey) || "")) {
        return;
    }
    if (config.sessionCookie && cookie(config.sessionCookie) === "") {
        return;
    }

    var lifetime = Number(config.lifetime);
    var expirable = config.expirable instanceof Array ? config.expirable : [];
    var now = Math.floor(new Date().getTime() / 1000);

    function section(name) {
        var value = sections[name];
        if (!value || typeof value !== "object" || value instanceof Array) {
            return null;
        }
        if (!isFinite(lifetime) || lifetime <= 0) {
            return value;
        }
        var stamp = Number(value.data_id);
        if (!isFinite(stamp) || stamp <= 0) {
            return value;
        }
        var ages = false;
        for (var i = 0; i < expirable.length; i++) {
            if (expirable[i] === name) {
                ages = true;
                break;
            }
        }
        return ages && stamp + lifetime <= now ? null : value;
    }

    function size(value) {
        if (!value || typeof value !== "object") {
            return 0;
        }
        return value instanceof Array ? value.length : Object.keys(value).length;
    }

    var rules = [];
    var counters = config.counters instanceof Array ? config.counters : [];
    for (var c = 0; c < counters.length; c++) {
        var counter = counters[c];
        var counted = section(counter.section);
        if (!counted) {
            continue;
        }
        var total =
            counter.kind === "size" ? size(counted[counter.field]) : Number(counted[counter.field]);
        if (!isFinite(total) || total < 0) {
            total = 0;
        }
        total = Math.floor(total);
        root.style.setProperty(counter.property, String(total));
        root.setAttribute(counter.attribute, total > 0 ? "1" : "0");

        if (total > 0 && counter.island) {
            var target =
                '[data-mage-island]:not([data-mage-island-ready])[data-component$="' +
                counter.island +
                '"]';
            rules.push(target + "{position:relative}");
            rules.push(target + '::after{content:"' + total + '"}');
        }
    }

    if (rules.length && doc.head) {
        var style = doc.createElement("style");
        style.setAttribute("data-mo-prepaint", "");
        style.appendChild(doc.createTextNode(rules.join("")));
        doc.head.appendChild(style);
    }

    var flags = config.flags instanceof Array ? config.flags : [];
    for (var f = 0; f < flags.length; f++) {
        var flag = flags[f];
        var flagged = section(flag.section);
        if (!flagged) {
            continue;
        }
        root.setAttribute(flag.attribute, flagged[flag.field] ? "1" : "0");
    }
})(typeof window !== "undefined" ? window : undefined);
