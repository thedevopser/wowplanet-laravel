<template>
    <div class="space-y-8 animate-in fade-in duration-500">
        <div class="text-center max-w-3xl mx-auto">
            <h2 class="text-3xl md:text-4xl font-black mb-3">
                <span class="bg-clip-text text-transparent bg-linear-to-r from-red-300 via-red-400 to-red-600">Administration</span>
            </h2>
            <p class="text-slate-400 text-sm md:text-base">Panneau de gestion WowPlanet</p>
        </div>

        <!-- Section 1: Database imports -->
        <div class="bg-slate-800/30 rounded-2xl border border-white/5 p-5 sm:p-8">
            <h3 class="text-lg font-bold text-white mb-4">Mise a jour des donnees</h3>

            <div class="space-y-4">
                <!-- Download DB2 -->
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        @click="runImport('app:download-db2')"
                        :disabled="importLoading"
                        class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors"
                    >
                        Telecharger DB2
                    </button>
                    <span class="text-slate-500 text-xs">Telecharge les CSV DB2 + fichiers SimpleArmory</span>
                </div>

                <!-- Import data -->
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        @click="runImport('app:wow-data-import', importType)"
                        :disabled="importLoading"
                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors"
                    >
                        Importer les donnees
                    </button>
                    <select
                        v-model="importType"
                        class="bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white"
                    >
                        <option value="all">Tout</option>
                        <option value="achievements">Hauts-faits</option>
                        <option value="quests">Quetes</option>
                        <option value="mounts">Montures</option>
                        <option value="pets">Mascottes</option>
                        <option value="professions">Professions</option>
                        <option value="decor">Decorations</option>
                    </select>
                </div>

                <!-- Refresh data -->
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        @click="runImport('app:wow-data-refresh', refreshType)"
                        :disabled="importLoading"
                        class="px-4 py-2 rounded-lg bg-orange-600 hover:bg-orange-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors"
                    >
                        Rafraichir les donnees
                    </button>
                    <select
                        v-model="refreshType"
                        class="bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white"
                    >
                        <option value="all">Tout</option>
                        <option value="achievements">Hauts-faits</option>
                        <option value="quests">Quetes</option>
                        <option value="mounts">Montures</option>
                        <option value="pets">Mascottes</option>
                        <option value="professions">Professions</option>
                        <option value="decor">Decorations</option>
                    </select>
                    <span class="text-red-400 text-xs">Attention : supprime et reimporte les donnees</span>
                </div>

                <!-- Tag factions -->
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        @click="runImport('app:wow-quest-faction-tag')"
                        :disabled="importLoading"
                        class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors"
                    >
                        Tagger factions quetes
                    </button>
                    <span class="text-slate-500 text-xs">Tague les quetes miroir via l'API Blizzard</span>
                </div>

                <!-- Import status -->
                <div v-if="importJobId" class="mt-4 p-4 bg-slate-900/50 rounded-xl border border-white/5">
                    <div class="flex items-center gap-2 mb-2">
                        <div
                            :class="[
                                'w-2 h-2 rounded-full',
                                importStatus === 'completed' ? 'bg-green-400' :
                                importStatus === 'failed' ? 'bg-red-400' :
                                'bg-amber-400 animate-pulse'
                            ]"
                        ></div>
                        <span class="text-sm text-slate-300 font-medium">
                            {{ importStatus === 'pending' ? 'En attente...' :
                               importStatus === 'running' ? 'Import en cours...' :
                               importStatus === 'completed' ? 'Termine' :
                               importStatus === 'failed' ? 'Echec' : 'Inconnu' }}
                        </span>
                    </div>
                    <pre v-if="importOutput" class="text-xs text-slate-400 whitespace-pre-wrap max-h-64 overflow-y-auto font-mono">{{ importOutput }}</pre>
                </div>
            </div>
        </div>

        <!-- Section 2: Cache -->
        <div class="bg-slate-800/30 rounded-2xl border border-white/5 p-5 sm:p-8">
            <h3 class="text-lg font-bold text-white mb-4">Cache</h3>
            <div class="flex flex-wrap items-center gap-3">
                <button
                    @click="clearCache"
                    :disabled="cacheLoading"
                    class="px-4 py-2 rounded-lg bg-teal-600 hover:bg-teal-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors"
                >
                    {{ cacheLoading ? 'Nettoyage...' : 'Vider les caches' }}
                </button>
            </div>
            <pre v-if="cacheOutput" class="mt-4 text-xs text-slate-400 whitespace-pre-wrap font-mono p-4 bg-slate-900/50 rounded-xl border border-white/5">{{ cacheOutput }}</pre>
        </div>

        <!-- Section 3: Maintenance -->
        <div class="bg-slate-800/30 rounded-2xl border border-white/5 p-5 sm:p-8">
            <h3 class="text-lg font-bold text-white mb-4">Mode maintenance</h3>

            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div :class="['w-3 h-3 rounded-full', maintenanceActive ? 'bg-red-400 animate-pulse' : 'bg-green-400']"></div>
                    <span class="text-sm text-slate-300">
                        {{ maintenanceActive ? 'Mode maintenance actif' : 'Application en ligne' }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        v-if="!maintenanceActive"
                        @click="toggleMaintenance(true)"
                        :disabled="maintenanceLoading"
                        class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors"
                    >
                        Activer la maintenance
                    </button>
                    <button
                        v-else
                        @click="toggleMaintenance(false)"
                        :disabled="maintenanceLoading"
                        class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors"
                    >
                        Desactiver la maintenance
                    </button>
                </div>

                <div v-if="maintenanceBypassUrl" class="p-3 bg-slate-900/50 rounded-xl border border-white/5">
                    <p class="text-xs text-slate-400 mb-1">URL de bypass (visitez cette URL pour acceder au site en maintenance) :</p>
                    <a :href="maintenanceBypassUrl" class="text-sm text-blue-400 hover:text-blue-300 break-all">{{ maintenanceBypassUrl }}</a>
                </div>
            </div>
        </div>

        <!-- Section 4: Discord -->
        <div class="bg-slate-800/30 rounded-2xl border border-white/5 p-5 sm:p-8">
            <h3 class="text-lg font-bold text-white mb-4">Message Discord</h3>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Canal</label>
                        <select
                            v-model="discord.channel"
                            class="w-full bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white"
                        >
                            <option value="changelog">Changelog</option>
                            <option value="discussion">Discussion</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Couleur</label>
                        <div class="flex gap-2">
                            <button
                                v-for="c in colorPresets"
                                :key="c.value"
                                @click="discord.color = c.value"
                                :class="[
                                    'w-8 h-8 rounded-lg border-2 transition-all',
                                    discord.color === c.value ? 'border-white scale-110' : 'border-transparent opacity-70 hover:opacity-100'
                                ]"
                                :style="{ backgroundColor: c.hex }"
                                :title="c.name"
                            ></button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Titre</label>
                    <input
                        v-model="discord.title"
                        type="text"
                        maxlength="256"
                        class="w-full bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500"
                        placeholder="Titre de l'embed"
                    >
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Description</label>
                    <textarea
                        v-model="discord.description"
                        rows="4"
                        maxlength="4096"
                        class="w-full bg-slate-700 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 resize-y"
                        placeholder="Contenu du message (supporte le Markdown Discord)"
                    ></textarea>
                </div>

                <!-- Fields -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs text-slate-400">Champs (optionnel)</label>
                        <button
                            @click="addField"
                            class="text-xs text-blue-400 hover:text-blue-300 transition-colors"
                        >
                            + Ajouter un champ
                        </button>
                    </div>
                    <div v-for="(field, i) in discord.fields" :key="i" class="flex gap-2 mb-2">
                        <input
                            v-model="field.name"
                            type="text"
                            maxlength="256"
                            class="flex-1 bg-slate-700 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white placeholder-slate-500"
                            placeholder="Nom"
                        >
                        <input
                            v-model="field.value"
                            type="text"
                            maxlength="1024"
                            class="flex-1 bg-slate-700 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white placeholder-slate-500"
                            placeholder="Valeur"
                        >
                        <label class="flex items-center gap-1 text-xs text-slate-400 whitespace-nowrap">
                            <input type="checkbox" v-model="field.inline" class="rounded border-slate-600">
                            Inline
                        </label>
                        <button @click="discord.fields.splice(i, 1)" class="text-red-400 hover:text-red-300 text-sm px-1">X</button>
                    </div>
                </div>

                <!-- Preview -->
                <div v-if="discord.title || discord.description" class="p-4 rounded-lg border-l-4" :style="{ borderColor: currentColorHex, backgroundColor: 'rgba(30,33,36,0.9)' }">
                    <p v-if="discord.title" class="font-semibold text-white text-sm mb-1">{{ discord.title }}</p>
                    <div v-if="discord.description" class="discord-markdown text-slate-300 text-xs" v-html="renderedDescription"></div>
                    <div v-if="discord.fields.length" class="mt-2 grid gap-1" :class="discord.fields.some(f => f.inline) ? 'grid-cols-3' : 'grid-cols-1'">
                        <div v-for="(field, i) in discord.fields" :key="i" :class="field.inline ? '' : 'col-span-3'">
                            <p class="text-xs font-semibold text-white">{{ field.name }}</p>
                            <p class="text-xs text-slate-400">{{ field.value }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="sendDiscord"
                        :disabled="discordLoading || !discord.title || !discord.description"
                        class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors"
                    >
                        {{ discordLoading ? 'Envoi...' : 'Envoyer' }}
                    </button>
                    <span v-if="discordResult !== null" :class="discordResult ? 'text-green-400' : 'text-red-400'" class="text-sm">
                        {{ discordResult ? 'Envoye avec succes' : 'Echec de l\'envoi' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useCharacterStore } from '../stores/character';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import axios from 'axios';

marked.setOptions({
    breaks: true,
    gfm: true,
});

const router = useRouter();
const store = useCharacterStore();

onMounted(() => {
    if (!store.isAdmin) {
        router.push('/');
        return;
    }
    fetchStatus();
});

// === Status ===
const maintenanceActive = ref(false);
const maintenanceLoading = ref(false);
const maintenanceBypassUrl = ref('');

const fetchStatus = async () => {
    try {
        const response = await axios.get('/api/admin/status');
        maintenanceActive.value = response.data.maintenance;
    } catch {
        // ignore
    }
};

// === Imports ===
const importType = ref('all');
const refreshType = ref('all');
const importLoading = ref(false);
const importJobId = ref(null);
const importStatus = ref('');
const importOutput = ref('');
let pollTimer = null;

const runImport = async (command, type = null) => {
    importLoading.value = true;
    importJobId.value = null;
    importStatus.value = '';
    importOutput.value = '';

    try {
        const payload = { command };
        if (type && command !== 'app:download-db2' && command !== 'app:wow-quest-faction-tag') {
            payload.type = type;
        }
        const response = await axios.post('/api/admin/import', payload);
        importJobId.value = response.data.jobId;
        importStatus.value = 'pending';
        startPolling();
    } catch (err) {
        importOutput.value = err.response?.data?.message || 'Erreur lors du lancement';
        importLoading.value = false;
    }
};

const startPolling = () => {
    stopPolling();
    pollTimer = setInterval(async () => {
        if (!importJobId.value) return;
        try {
            const response = await axios.get(`/api/admin/import/${importJobId.value}`);
            importStatus.value = response.data.status;
            if (response.data.output) {
                importOutput.value = response.data.output;
            }
            if (response.data.status === 'completed' || response.data.status === 'failed') {
                stopPolling();
                importLoading.value = false;
            }
        } catch {
            stopPolling();
            importLoading.value = false;
        }
    }, 3000);
};

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

onUnmounted(() => {
    stopPolling();
});

// === Cache ===
const cacheLoading = ref(false);
const cacheOutput = ref('');

const clearCache = async () => {
    cacheLoading.value = true;
    cacheOutput.value = '';
    try {
        const response = await axios.post('/api/admin/clear-cache');
        cacheOutput.value = response.data.output;
    } catch (err) {
        cacheOutput.value = err.response?.data?.message || 'Erreur';
    } finally {
        cacheLoading.value = false;
    }
};

// === Maintenance ===
const generateSecret = () => {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < 32; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
};

const toggleMaintenance = async (enable) => {
    maintenanceLoading.value = true;
    const secret = enable ? generateSecret() : null;

    try {
        const response = await axios.post('/api/admin/maintenance', { enable, secret });
        maintenanceActive.value = response.data.maintenance;

        if (enable && secret) {
            maintenanceBypassUrl.value = `${window.location.origin}/${secret}`;
        } else {
            maintenanceBypassUrl.value = '';
        }
    } catch {
        // ignore
    } finally {
        maintenanceLoading.value = false;
    }
};

// === Discord ===
const colorPresets = [
    { name: 'Bleu', value: 3447003, hex: '#3498db' },
    { name: 'Vert', value: 3066993, hex: '#2ecc71' },
    { name: 'Rouge', value: 15158332, hex: '#e74c3c' },
    { name: 'Orange', value: 15105570, hex: '#e67e22' },
    { name: 'Violet', value: 10181046, hex: '#9b59b6' },
    { name: 'Or', value: 15844367, hex: '#f1c40f' },
];

const discord = reactive({
    channel: 'changelog',
    title: '',
    description: '',
    color: 3447003,
    fields: [],
});

const discordLoading = ref(false);
const discordResult = ref(null);

const currentColorHex = computed(() => {
    const preset = colorPresets.find(c => c.value === discord.color);
    return preset ? preset.hex : '#3498db';
});

const renderedDescription = computed(() => {
    if (!discord.description) return '';
    return DOMPurify.sanitize(marked(discord.description));
});

const addField = () => {
    discord.fields.push({ name: '', value: '', inline: false });
};

const sendDiscord = async () => {
    discordLoading.value = true;
    discordResult.value = null;

    try {
        const payload = {
            channel: discord.channel,
            title: discord.title,
            description: discord.description,
            color: discord.color,
        };
        const filledFields = discord.fields.filter(f => f.name && f.value);
        if (filledFields.length) {
            payload.fields = filledFields;
        }

        const response = await axios.post('/api/admin/discord', payload);
        discordResult.value = response.data.success;

        if (response.data.success) {
            discord.title = '';
            discord.description = '';
            discord.fields = [];
        }
    } catch {
        discordResult.value = false;
    } finally {
        discordLoading.value = false;
        setTimeout(() => { discordResult.value = null; }, 5000);
    }
};
</script>

<style scoped>
.discord-markdown :deep(h1),
.discord-markdown :deep(h2),
.discord-markdown :deep(h3) {
    color: white;
    font-weight: 700;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
}
.discord-markdown :deep(h1) { font-size: 1rem; }
.discord-markdown :deep(h2) { font-size: 0.875rem; }
.discord-markdown :deep(h3) { font-size: 0.8rem; }
.discord-markdown :deep(p) {
    margin-bottom: 0.25rem;
}
.discord-markdown :deep(strong) {
    color: white;
    font-weight: 700;
}
.discord-markdown :deep(em) {
    font-style: italic;
}
.discord-markdown :deep(a) {
    color: #00aff4;
    text-decoration: none;
}
.discord-markdown :deep(a:hover) {
    text-decoration: underline;
}
.discord-markdown :deep(code) {
    background: rgba(0, 0, 0, 0.3);
    padding: 0.1rem 0.3rem;
    border-radius: 3px;
    font-size: 0.75rem;
    font-family: 'Consolas', 'Monaco', monospace;
}
.discord-markdown :deep(pre) {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 4px;
    padding: 0.5rem;
    margin: 0.25rem 0;
    overflow-x: auto;
}
.discord-markdown :deep(pre code) {
    background: none;
    padding: 0;
}
.discord-markdown :deep(blockquote) {
    border-left: 3px solid rgba(255, 255, 255, 0.2);
    padding-left: 0.5rem;
    margin: 0.25rem 0;
    color: #b9bbbe;
}
.discord-markdown :deep(ul),
.discord-markdown :deep(ol) {
    padding-left: 1.25rem;
    margin: 0.25rem 0;
}
.discord-markdown :deep(li) {
    margin-bottom: 0.1rem;
}
.discord-markdown :deep(hr) {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin: 0.5rem 0;
}
</style>
