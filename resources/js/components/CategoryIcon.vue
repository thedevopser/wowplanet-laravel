<template>
    <svg class="w-full h-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <!-- Quêtes: parchemin -->
        <template v-if="category === 'quests'">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
            <polyline points="10 9 9 9 8 9" />
        </template>

        <!-- Hauts-faits: trophée -->
        <template v-else-if="category === 'achievements'">
            <path d="M6 9H4.5a2.5 2.5 0 010-5H6" />
            <path d="M18 9h1.5a2.5 2.5 0 000-5H18" />
            <path d="M4 22h16" />
            <path d="M10 22V18a4 4 0 00-4-4V4h12v10a4 4 0 00-4 4v4" />
        </template>

        <!-- Montures: fer-à-cheval -->
        <template v-else-if="category === 'mounts'">
            <path d="M5 20a3 3 0 01-1-5.83V7a5 5 0 0110 0v7.17A3 3 0 0115 20" />
            <path d="M9.5 2v2" />
            <circle cx="7" cy="17" r="1" fill="currentColor" />
            <circle cx="12" cy="17" r="1" fill="currentColor" />
            <path d="M19 20a3 3 0 01-1-5.83V7" />
        </template>

        <!-- Mascottes: patte -->
        <template v-else-if="category === 'pets'">
            <circle cx="11" cy="4" r="2" fill="currentColor" />
            <circle cx="18" cy="8" r="2" fill="currentColor" />
            <circle cx="20" cy="14" r="2" fill="currentColor" />
            <circle cx="4" cy="8" r="2" fill="currentColor" />
            <circle cx="2" cy="14" r="2" fill="currentColor" />
            <path d="M15.05 17.53a5.96 5.96 0 00-8.1 0l-.42.42a2 2 0 000 2.83l.21.21a2 2 0 002.83 0l.42-.42a2 2 0 012.83 0l.42.42a2 2 0 002.83 0l.21-.21a2 2 0 000-2.83z" fill="currentColor" />
        </template>

        <!-- Décorations: maison -->
        <template v-else-if="category === 'decor'">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
        </template>

        <!-- Professions: marteau -->
        <template v-else-if="category === 'professions'">
            <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
        </template>

        <!-- Fallback: étoile -->
        <template v-else>
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        </template>
    </svg>
</template>

<script setup>
defineProps({
    category: { type: String, required: true },
});
</script>
