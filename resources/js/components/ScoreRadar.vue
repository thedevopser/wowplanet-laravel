<template>
    <div class="relative" :style="{ width: size + 'px', height: size + 'px', overflow: 'visible' }">
        <svg :viewBox="`0 0 ${size} ${size}`" class="w-full h-full" overflow="visible">
            <!-- Grid lines -->
            <polygon
                v-for="level in [25, 50, 75, 100]"
                :key="level"
                :points="gridPoints(level)"
                fill="none"
                stroke="currentColor"
                :stroke-width="level === 100 ? 1 : 0.5"
                class="text-slate-700"
            />

            <!-- Axis lines -->
            <line
                v-for="(_, i) in axes"
                :key="'axis-' + i"
                :x1="center"
                :y1="center"
                :x2="axisPoint(i, 100).x"
                :y2="axisPoint(i, 100).y"
                stroke="currentColor"
                stroke-width="0.5"
                class="text-slate-700/50"
            />

            <!-- Data polygon -->
            <polygon
                :points="dataPoints"
                fill="currentColor"
                fill-opacity="0.15"
                stroke="currentColor"
                stroke-width="2"
                stroke-linejoin="round"
                class="text-blue-500 transition-all duration-1000"
            />

            <!-- Data points -->
            <circle
                v-for="(axis, i) in axes"
                :key="'point-' + i"
                :cx="axisPoint(i, axis.score).x"
                :cy="axisPoint(i, axis.score).y"
                r="3"
                fill="currentColor"
                class="text-blue-400 transition-all duration-1000"
            />

            <!-- Labels -->
            <text
                v-for="(axis, i) in axes"
                :key="'label-' + i"
                :x="labelPoint(i).x"
                :y="labelPoint(i).y"
                :text-anchor="labelAnchor(i)"
                dominant-baseline="middle"
                class="fill-slate-400 text-[10px] sm:text-xs font-bold"
            >
                {{ axis.label }}
            </text>

            <!-- Score values -->
            <text
                v-for="(axis, i) in axes"
                :key="'value-' + i"
                :x="labelPoint(i).x"
                :y="labelPoint(i).y + 14"
                :text-anchor="labelAnchor(i)"
                dominant-baseline="middle"
                class="fill-slate-500 text-[9px] font-mono"
            >
                {{ Math.round(axis.score) }}%
            </text>
        </svg>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    axes: { type: Array, required: true },
    size: { type: Number, default: 320 },
});

const center = computed(() => props.size / 2);
const radius = computed(() => props.size / 2 - 45);

function axisPoint(index, value) {
    const angle = (2 * Math.PI * index) / props.axes.length - Math.PI / 2;
    const r = (value / 100) * radius.value;
    return {
        x: center.value + r * Math.cos(angle),
        y: center.value + r * Math.sin(angle),
    };
}

function labelPoint(index) {
    const angle = (2 * Math.PI * index) / props.axes.length - Math.PI / 2;
    const r = radius.value + 28;
    return {
        x: center.value + r * Math.cos(angle),
        y: center.value + r * Math.sin(angle),
    };
}

function labelAnchor(index) {
    const angle = (2 * Math.PI * index) / props.axes.length - Math.PI / 2;
    const x = Math.cos(angle);
    if (x < -0.1) return 'end';
    if (x > 0.1) return 'start';
    return 'middle';
}

function gridPoints(level) {
    return props.axes
        .map((_, i) => {
            const p = axisPoint(i, level);
            return `${p.x},${p.y}`;
        })
        .join(' ');
}

const dataPoints = computed(() =>
    props.axes
        .map((axis, i) => {
            const p = axisPoint(i, axis.score);
            return `${p.x},${p.y}`;
        })
        .join(' ')
);
</script>
