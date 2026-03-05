<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape="$emit('close')">
            <div class="absolute inset-0 bg-black/70" @click="$emit('close')"></div>
            <div class="relative bg-slate-900 border border-white/10 rounded-2xl shadow-2xl max-w-3xl w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white">
                        {{ variant === 'personal' ? 'Partager mon score' : 'Partager le score compte' }}
                    </h3>
                    <button @click="$emit('close')" class="text-slate-500 hover:text-white transition-colors p-1">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex justify-center bg-slate-950/50 rounded-xl p-4 overflow-auto">
                    <canvas ref="canvasRef" class="max-w-full h-auto rounded-lg"></canvas>
                </div>

                <div class="flex items-center justify-center gap-3">
                    <button @click="downloadImage" class="px-5 py-2.5 rounded-xl text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors flex items-center gap-2 border border-indigo-400/30">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" /></svg>
                        Telecharger
                    </button>
                    <button @click="copyImage" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition-colors flex items-center gap-2 border border-white/10" :class="copied ? 'bg-green-600 border-green-400/30' : 'bg-slate-700 hover:bg-slate-600'">
                        <svg v-if="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        {{ copied ? 'Copie !' : 'Copier l\'image' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';
import { renderScoreCard } from '../utils/scoreCardRenderer';

const props = defineProps({
    show: Boolean,
    variant: { type: String, default: 'personal' },
    scoreData: { type: Object, default: () => ({}) },
});

defineEmits(['close']);

const canvasRef = ref(null);
const copied = ref(false);

watch(() => props.show, async (visible) => {
    if (!visible) return;
    await nextTick();
    const rendered = renderScoreCard(props.scoreData);
    const target = canvasRef.value;
    if (!target) return;
    target.width = rendered.width;
    target.height = rendered.height;
    target.getContext('2d').drawImage(rendered, 0, 0);
});

function downloadImage() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const url = canvas.toDataURL('image/png');
    const a = document.createElement('a');
    a.href = url;
    a.download = `wowplanet-score-${props.variant}.png`;
    a.click();
}

async function copyImage() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    try {
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
        copied.value = true;
    } catch {
        downloadImage();
        return;
    }
    setTimeout(() => { copied.value = false; }, 2500);
}
</script>
