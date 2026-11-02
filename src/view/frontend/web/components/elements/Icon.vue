<!--
 This file is part of the MageObsidian - ModernFrontend project.

 SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { computed } from "vue";
import { PageConfig, readPageConfig } from "mage-obsidian/runtime/pageConfig.ts";

// Client-side counterpart of the `hero_icon` helper. Both emit the SAME
// `<svg><use href>` so an island whose server markup contains an icon can be
// adopted instead of re-rendered — which is why this references the sprite by
// URL rather than importing @heroicons/vue, whose inline <path> could never
// match what PHP produced.
//
// The base URL carries the static version and the resolved theme, so only the
// server can know it; IconRuntime publishes it.

const props = withDefaults(
    defineProps<{
        name: string;
        set?: "solid" | "outline";
        size?: number | string;
    }>(),
    { set: "solid", size: 24 },
);

const OUTLINE = "outline";

const file = computed(() => (props.name.endsWith(".svg") ? props.name : `${props.name}.svg`));

const href = computed(() => {
    const base = (readPageConfig(PageConfig.Icons) as { baseUrl?: string } | undefined)?.baseUrl ?? "";
    return `${base}${props.size}/${props.set}/${file.value}#icon`;
});

const paint = computed(() =>
    props.set === OUTLINE
        ? { fill: "none", stroke: "currentColor", "stroke-width": "1.5" }
        : { fill: "currentColor" },
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 ${size} ${size}`"
        v-bind="paint"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
    >
        <use :href="href"></use>
    </svg>
</template>
