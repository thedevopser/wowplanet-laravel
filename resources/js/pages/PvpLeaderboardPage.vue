<template>
    <div class="space-y-6 py-6 sm:py-8">
        <Head>
            <title>{{ meta.title }}</title>
            <meta name="description" :content="meta.description">
            <link rel="canonical" :href="meta.canonicalUrl">
            <meta property="og:type" :content="meta.ogType">
            <meta property="og:title" :content="meta.ogTitle">
            <meta property="og:description" :content="meta.ogDescription">
            <meta property="og:image" :content="meta.ogImage">
            <meta property="og:url" :content="meta.ogUrl">
            <meta property="og:site_name" content="WowPlanet">
            <meta property="og:locale" content="fr_FR">
        </Head>

        <DatabasePageHeader
            title="Classements PvP"
            :subtitle="seasonId ? `${label} — saison ${seasonId}` : label"
            :count="total"
            count-label="joueurs classés"
            accent-color="amber"
        />

        <!-- Sélecteur à deux niveaux : le mode, puis le bracket du mode actif.
             Mêlée solo et Blitz comptent une quarantaine de brackets chacun : les
             étaler en boutons noierait les quatre modes. -->
        <div v-if="groups.length" class="space-y-3">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="group in groups"
                    :key="group.key"
                    type="button"
                    :data-testid="`pvp-mode-${group.key}`"
                    :aria-pressed="group.key === activeGroupKey"
                    :class="[
                        'px-3.5 py-2 rounded-lg text-xs sm:text-sm font-bold border transition-all',
                        group.key === activeGroupKey
                            ? 'bg-amber-500/15 border-amber-500/40 text-amber-300'
                            : 'bg-slate-800/60 border-white/10 text-slate-400 hover:text-slate-200 hover:border-white/20'
                    ]"
                    @click="onModeChange(group)"
                >
                    {{ group.label }}
                </button>
            </div>

            <!-- Second niveau : boutons quand ils tiennent, liste déroulante sinon. -->
            <div v-if="activeGroup && activeGroup.brackets.length > 1" class="flex flex-wrap items-center gap-2">
                <template v-if="activeGroup.brackets.length <= INLINE_OPTIONS_MAX">
                    <button
                        v-for="option in activeGroup.brackets"
                        :key="option.slug"
                        type="button"
                        :data-testid="`pvp-bracket-option-${option.slug}`"
                        :aria-pressed="option.slug === bracket"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-medium border transition-all',
                            option.slug === bracket
                                ? 'bg-amber-500/15 border-amber-500/40 text-amber-300'
                                : 'bg-slate-800/60 border-white/10 text-slate-400 hover:text-slate-200 hover:border-white/20'
                        ]"
                        @click="onBracketChange(option.slug)"
                    >
                        {{ option.short }}
                    </button>
                </template>

                <select
                    v-else
                    data-testid="pvp-bracket-select"
                    :value="bracket"
                    aria-label="Spécialisation"
                    class="w-full sm:w-80 px-3 py-2 rounded-lg text-xs sm:text-sm bg-slate-800/60 border border-white/10 text-slate-200 focus:border-amber-500/40 focus:outline-none"
                    @change="onBracketChange($event.target.value)"
                >
                    <option v-for="option in activeGroup.brackets" :key="option.slug" :value="option.slug">
                        {{ option.short }}
                    </option>
                </select>
            </div>
        </div>

        <SearchFilter
            v-model:search="searchTerm"
            placeholder="Rechercher un joueur ou un royaume..."
            :show-hide-toggle="false"
            :debounce-ms="300"
            @search-debounced="onSearchDebounced"
        />

        <!-- Classement indisponible -->
        <div v-if="unavailable" class="card-glass rounded-2xl sm:rounded-3xl border p-8 text-center">
            <p class="text-slate-400 text-sm">Classement momentanément indisponible.</p>
            <p class="text-slate-600 text-xs mt-2">L'API Blizzard n'a pas répondu, réessayez dans quelques minutes.</p>
        </div>

        <template v-else>
            <div v-if="entries.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10">
                            <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 w-14">Rang</th>
                            <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3">Joueur</th>
                            <th class="text-left text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 hidden sm:table-cell">Royaume</th>
                            <th class="text-right text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3">Cote</th>
                            <th class="text-right text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider pb-3 hidden md:table-cell">V / D</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in entries"
                            :key="`${entry.realm_slug}-${entry.name}`"
                            :data-testid="`pvp-rank-${entry.rank}`"
                            class="border-b border-white/3 even:bg-slate-800/20 hover:bg-slate-800/40 transition-colors"
                        >
                            <td class="py-2 font-mono text-xs text-slate-500">{{ entry.rank }}</td>
                            <td class="py-2">
                                <Link
                                    :href="`/character/${entry.realm_slug}/${entry.name.toLowerCase()}`"
                                    :class="['hover:underline', factionClass(entry.faction)]"
                                >{{ entry.name }}</Link>
                                <span class="text-slate-600 text-xs sm:hidden ml-1">{{ entry.realm }}</span>
                            </td>
                            <td class="py-2 text-slate-500 text-xs hidden sm:table-cell">{{ entry.realm }}</td>
                            <td class="py-2 text-right font-mono font-bold text-amber-400">{{ entry.rating }}</td>
                            <td class="py-2 text-right font-mono text-xs hidden md:table-cell">
                                <span class="text-emerald-400">{{ entry.won }}</span>
                                <span class="text-slate-600"> / </span>
                                <span class="text-rose-400">{{ entry.lost }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-8 text-slate-500 text-sm">Aucun résultat trouvé.</div>

            <DatabasePagination
                :current-page="currentPage"
                :last-page="lastPage"
                :total="total"
                @page-change="onPageChange"
            />
        </template>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SearchFilter from '../components/SearchFilter.vue';
import DatabasePageHeader from '../components/DatabasePageHeader.vue';
import DatabasePagination from '../components/DatabasePagination.vue';

const props = defineProps({
    meta: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
    bracket: { type: String, required: true },
    label: { type: String, default: '' },
    seasonId: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    unavailable: { type: Boolean, default: false },
    search: { type: String, default: null },
});

const searchTerm = ref(props.search ?? '');

// Au-delà, le second niveau passe en liste déroulante.
const INLINE_OPTIONS_MAX = 6;

const activeGroup = computed(
    () => props.groups.find(group => group.brackets.some(b => b.slug === props.bracket)) ?? props.groups[0] ?? null,
);

const activeGroupKey = computed(() => activeGroup.value?.key ?? null);

// Le classement complet transite en props : seules ces clés sont rechargées.
const dataOnly = ['entries', 'total', 'currentPage', 'lastPage', 'unavailable', 'search', 'bracket', 'label'];

function basePath() {
    return `/classements-pvp/${props.bracket}`;
}

function factionClass(faction) {
    if (faction === 'HORDE') return 'text-rose-400';
    if (faction === 'ALLIANCE') return 'text-blue-400';
    return 'text-slate-300';
}

// Changer de bracket change la page : on repart de zéro plutôt que de traîner
// la recherche et la pagination du bracket précédent.
function onBracketChange(slug) {
    if (slug === props.bracket) return;

    searchTerm.value = '';
    router.get(`/classements-pvp/${slug}`, {}, { preserveState: false });
}

// Choisir un mode charge son classement d'entrée : « toutes spés » quand Blizzard
// le publie (mêlée solo, blitz), sinon le premier bracket du mode.
function onModeChange(group) {
    if (group.key === activeGroupKey.value) return;

    onBracketChange(group.brackets[0].slug);
}

function onPageChange(newPage) {
    router.get(basePath(), { page: newPage, search: searchTerm.value || undefined }, {
        preserveState: true,
        only: dataOnly,
    });
}

function onSearchDebounced(value) {
    router.get(basePath(), { page: 1, search: value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        only: dataOnly,
    });
}
</script>
