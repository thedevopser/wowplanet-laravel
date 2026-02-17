<template>
    <div class="min-h-screen dark bg-slate-950 text-slate-100 font-sans selection:bg-blue-500/30">
        <!-- Header / Search -->
        <header class="border-b border-white/10 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
                <a href="/" @click.prevent="goHome" class="flex items-center gap-3 cursor-pointer hover:opacity-80 transition-opacity">
                    <img src="/images/logo.png" alt="WowPlanet Logo" class="w-12 h-12 rounded-xl shadow-lg shadow-blue-500/30 object-cover">
                    <h1 class="text-2xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-200 to-blue-500">WowPlanet</h1>
                </a>

                <div class="flex items-center gap-3">
                    <!-- Auth buttons -->
                    <template v-if="store.isAuthenticated">
                        <button
                            @click="showMyCharacters"
                            :class="[
                                'px-4 py-1.5 rounded-md text-sm font-semibold transition-all border',
                                store.currentView === 'my-characters'
                                    ? 'bg-blue-600 border-blue-400 text-white shadow-lg shadow-blue-500/20'
                                    : 'bg-slate-800/80 border-white/5 text-slate-300 hover:text-white hover:bg-slate-700'
                            ]"
                        >
                            Mes personnages
                        </button>
                        <button
                            @click="store.logout()"
                            class="px-3 py-1.5 rounded-md text-xs text-slate-500 hover:text-slate-300 transition-colors"
                        >
                            Déconnexion
                        </button>
                    </template>
                    <a
                        v-else
                        href="/auth/blizzard/redirect"
                        class="px-4 py-1.5 rounded-md text-sm font-semibold bg-slate-800/80 border border-white/5 text-slate-300 hover:text-white hover:bg-slate-700 transition-all"
                    >
                        Se connecter
                    </a>

                    <div class="w-px h-6 bg-white/10"></div>

                    <!-- Search -->
                    <input
                        v-model="search.realm"
                        type="text"
                        placeholder="Royaume"
                        class="bg-slate-800/80 border-none rounded-md px-3 py-1.5 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                    >
                    <input
                        v-model="search.name"
                        type="text"
                        placeholder="Nom du personnage"
                        @keyup.enter="searchCharacter"
                        class="bg-slate-800/80 border-none rounded-md px-3 py-1.5 focus:ring-2 focus:ring-blue-500 text-sm placeholder-slate-500"
                    >
                    <button
                        @click="searchCharacter"
                        :disabled="store.loading"
                        class="btn-gradient text-white font-semibold px-4 py-1.5 rounded-md text-sm shadow-lg shadow-blue-500/10"
                    >
                        {{ store.loading ? 'Recherche...' : 'Rechercher' }}
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            <div v-if="store.error" class="bg-red-500/10 border border-red-500/20 text-red-200 p-4 rounded-lg mb-6">
                {{ store.error }}
            </div>

            <!-- ============ LOADING SPINNER ============ -->
            <div v-if="store.loading" class="flex items-center justify-center py-16">
                <div class="card-glass rounded-2xl p-8 md:p-10 max-w-2xl mx-auto w-full">
                    <div class="text-center">
                        <div class="relative inline-block mb-6">
                            <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-blue-500 border-r-purple-500 animate-spin w-24 h-24"></div>
                            <div class="absolute inset-0 m-2 rounded-full border-4 border-transparent border-b-purple-500 border-l-blue-500 animate-spin-reverse w-20 h-20"></div>
                            <div class="relative flex items-center justify-center w-24 h-24">
                                <span class="text-4xl animate-pulse">&#9876;</span>
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold mb-3 text-white">Synchronisation en cours...</h3>
                        <p class="text-slate-400 mb-4">Analyse de votre personnage via l'API Blizzard</p>
                        <div class="flex justify-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                            <div class="w-3 h-3 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                            <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                        </div>
                        <p class="text-xs mt-6 text-slate-600">Qu&ecirc;tes, hauts-faits, montures, mascottes...</p>
                    </div>
                </div>
            </div>

            <!-- ============ CHARACTER DETAIL VIEW ============ -->
            <div v-else-if="store.currentView === 'character' && store.character" class="space-y-8 animate-in fade-in duration-500">
                <!-- Character Card -->
                <div class="relative overflow-hidden rounded-2xl card-glass border shadow-2xl">
                    <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-900/80 to-slate-900/40 z-10"></div>
                    <!-- Class icon as background -->
                    <img v-if="store.character.classIconUrl" :src="store.character.classIconUrl" class="absolute right-8 top-1/2 -translate-y-1/2 w-48 h-48 object-contain opacity-10" alt="">

                    <div class="relative z-20 p-8 flex flex-col md:flex-row items-start md:items-end gap-6">
                        <img :src="store.character.avatarUrl" class="w-24 h-24 rounded-xl border-2 shadow-2xl bg-slate-800 object-cover" :style="{ borderColor: classColor + '40' }" alt="">
                        <div class="flex-1 mb-2">
                            <h2 class="text-4xl font-black leading-tight mb-2" :style="{ color: classColor }">
                                {{ store.character.name }}
                            </h2>
                            <div class="flex flex-wrap items-center gap-3 text-slate-400 font-medium">
                                <span>Niv {{ store.character.level }}</span>
                                <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                                <span>{{ store.character.race }}</span>
                                <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                                <span class="font-bold tracking-wide uppercase text-xs" :style="{ color: classColor }">{{ store.character.class }}</span>
                                <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                                <span>{{ store.character.realm }}</span>
                            </div>
                        </div>

                        <!-- Global Stats -->
                        <div class="flex gap-4 mb-2">
                            <div class="bg-slate-900/50 backdrop-blur px-4 py-2 rounded-xl border border-white/5 text-center">
                                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Montures</div>
                                <div class="text-xl font-black text-amber-400">{{ store.character.mountsCount }}</div>
                            </div>
                            <div class="bg-slate-900/50 backdrop-blur px-4 py-2 rounded-xl border border-white/5 text-center">
                                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Mascottes</div>
                                <div class="text-xl font-black text-blue-400">{{ store.character.petsCount }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Type Tabs -->
                <div class="space-y-6">
                    <div class="flex gap-2 border-b border-white/10 pb-1">
                        <button
                            v-for="tab in contentTabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                'px-5 py-2.5 rounded-t-xl text-sm font-bold transition-all border-b-2 -mb-[5px]',
                                activeTab === tab.id
                                    ? 'text-white border-blue-500 bg-slate-900/50'
                                    : 'text-slate-500 border-transparent hover:text-slate-300 hover:border-slate-700'
                            ]"
                        >
                            {{ tab.label }}
                            <span v-if="tab.count !== undefined" class="ml-2 text-[10px] font-mono opacity-60">{{ tab.count }}</span>
                        </button>
                    </div>

                    <!-- ============ QUETES TAB ============ -->
                    <div v-if="activeTab === 'quests'" class="space-y-6">
                        <!-- Expansion Selector -->
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            <button
                                v-for="exp in store.expansions"
                                :key="exp.id"
                                @click="activeExpansion = exp.id"
                                :class="[
                                    'px-4 py-3 rounded-2xl text-[13px] font-bold transition-all border flex flex-col items-center gap-1 group relative overflow-hidden',
                                    activeExpansion === exp.id
                                        ? 'bg-blue-600 border-blue-400 text-white shadow-xl shadow-blue-500/20 scale-105 z-10'
                                        : 'bg-slate-900/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-800 hover:border-white/10'
                                ]"
                            >
                                <span class="relative z-10">{{ exp.name }}</span>
                                <div v-if="store.character.collections[exp.id]" :class="[
                                    'text-[9px] font-mono px-2 py-0.5 rounded-full border relative z-10',
                                    activeExpansion === exp.id ? 'bg-blue-700/50 border-white/20' : 'bg-slate-950 border-white/5 opacity-60'
                                ]">
                                    {{ store.character.collections[exp.id].quests.completed }} / {{ store.character.collections[exp.id].quests.total }}
                                </div>
                                <div v-if="store.character.collections[exp.id]" class="absolute bottom-0 left-0 h-0.5 bg-white/20 transition-all duration-500" :style="{ width: (store.character.collections[exp.id].quests.total > 0 ? (store.character.collections[exp.id].quests.completed / store.character.collections[exp.id].quests.total * 100) : 0) + '%' }"></div>
                            </button>
                        </div>

                        <!-- Quest Progress -->
                        <div v-if="currentCollection" class="space-y-8">
                            <div class="card-glass rounded-3xl border p-8 relative overflow-hidden group">
                                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/5 blur-3xl -mr-16 -mt-16"></div>
                                <div class="relative z-10">
                                    <div class="flex justify-between items-end mb-6">
                                        <div>
                                            <h3 class="text-2xl font-black text-white flex items-center gap-3">
                                                <div class="w-2 h-8 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50"></div>
                                                Quêtes
                                            </h3>
                                            <p class="text-slate-500 text-sm mt-1">Progression globale de l'extension</p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-3xl font-black text-blue-400 font-mono">
                                                {{ Math.round(currentCollection.quests.completed / (currentCollection.quests.total || 1) * 100) }}%
                                            </div>
                                            <div class="text-xs text-slate-500 font-mono uppercase font-bold">
                                                {{ currentCollection.quests.completed }} / {{ currentCollection.quests.total }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="h-3 bg-slate-900 rounded-full overflow-hidden border border-white/5">
                                        <div class="h-full bg-gradient-to-r from-blue-700 via-blue-500 to-blue-400 transition-all duration-1000 relative shadow-[0_0_15px_rgba(59,130,246,0.3)]" :style="{ width: (currentCollection.quests.total > 0 ? (currentCollection.quests.completed / currentCollection.quests.total * 100) : 0) + '%' }">
                                            <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Zones Drill-down -->
                            <section v-if="currentCollection.quests.zones?.length">
                                <div class="flex justify-between items-center mb-6">
                                    <h4 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                                        Décomposition par zone
                                        <div class="flex-1 h-px bg-slate-800"></div>
                                    </h4>
                                    <div v-if="totalPagesZones > 1" class="flex items-center gap-2 ml-4">
                                        <button @click="pageZones--" :disabled="pageZones === 1" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                                            <span class="text-xs text-slate-300">&larr;</span>
                                        </button>
                                        <span class="text-[10px] font-mono text-slate-500">{{ pageZones }} / {{ totalPagesZones }}</span>
                                        <button @click="pageZones++" :disabled="pageZones === totalPagesZones" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                                            <span class="text-xs text-slate-300">&rarr;</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-start">
                                    <div
                                        v-for="zone in paginatedZones"
                                        :key="zone.name"
                                        @click="toggleZone(zone)"
                                        class="bg-slate-900/40 border border-white/5 p-4 rounded-2xl hover:bg-slate-800/60 transition-colors group cursor-pointer"
                                    >
                                        <div class="flex justify-between items-start mb-3">
                                            <span class="text-sm font-bold text-slate-300 group-hover:text-blue-400 transition-colors">{{ zone.name }}</span>
                                            <span class="text-[10px] font-mono text-slate-500">{{ zone.completed }}/{{ zone.total }}</span>
                                        </div>
                                        <div class="h-1 bg-slate-950 rounded-full overflow-hidden">
                                            <div class="h-full bg-blue-600/80 transition-all duration-700" :style="{ width: (zone.completed / zone.total * 100) + '%' }"></div>
                                        </div>
                                        <div v-if="expandedZone === zone.name" class="mt-4 pt-4 border-t border-white/5 space-y-1 max-h-48 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                                            <div v-for="item in sortedItems(zone.items)" :key="item.id" class="flex justify-between items-center text-[10px] py-1">
                                                <a :href="`https://www.wowhead.com/fr/quest=${item.id}`" target="_blank" rel="noopener" @click.stop :class="[item.is_completed ? 'text-blue-400 font-medium' : 'text-slate-500', 'hover:underline']">{{ item.name }}</a>
                                                <span v-if="item.is_completed" class="text-green-500 font-bold">&check;</span>
                                                <span v-else class="text-slate-800">&cir;</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- ============ HAUTS-FAITS TAB ============ -->
                    <div v-if="activeTab === 'achievements'" class="space-y-6">
                        <!-- Expansion Selector -->
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            <button
                                v-for="exp in store.expansions"
                                :key="exp.id"
                                @click="activeExpansion = exp.id"
                                :class="[
                                    'px-4 py-3 rounded-2xl text-[13px] font-bold transition-all border flex flex-col items-center gap-1 group relative overflow-hidden',
                                    activeExpansion === exp.id
                                        ? 'bg-amber-600 border-amber-400 text-white shadow-xl shadow-amber-500/20 scale-105 z-10'
                                        : 'bg-slate-900/80 border-white/5 text-slate-400 hover:text-white hover:bg-slate-800 hover:border-white/10'
                                ]"
                            >
                                <span class="relative z-10">{{ exp.name }}</span>
                                <div v-if="store.character.collections[exp.id]" :class="[
                                    'text-[9px] font-mono px-2 py-0.5 rounded-full border relative z-10',
                                    activeExpansion === exp.id ? 'bg-amber-700/50 border-white/20' : 'bg-slate-950 border-white/5 opacity-60'
                                ]">
                                    {{ store.character.collections[exp.id].achievements.completed }} / {{ store.character.collections[exp.id].achievements.total }}
                                </div>
                                <div v-if="store.character.collections[exp.id]" class="absolute bottom-0 left-0 h-0.5 bg-white/20 transition-all duration-500" :style="{ width: (store.character.collections[exp.id].achievements.total > 0 ? (store.character.collections[exp.id].achievements.completed / store.character.collections[exp.id].achievements.total * 100) : 0) + '%' }"></div>
                            </button>
                        </div>

                        <!-- Achievement Progress -->
                        <div v-if="currentCollection" class="space-y-8">
                            <div class="card-glass rounded-3xl border p-8 relative overflow-hidden group">
                                <div class="absolute top-0 right-0 w-32 h-32 bg-amber-600/5 blur-3xl -mr-16 -mt-16"></div>
                                <div class="relative z-10">
                                    <div class="flex justify-between items-end mb-6">
                                        <div>
                                            <h3 class="text-2xl font-black text-white flex items-center gap-3">
                                                <div class="w-2 h-8 bg-amber-500 rounded-full shadow-lg shadow-amber-500/50"></div>
                                                Hauts-faits
                                            </h3>
                                            <p class="text-slate-500 text-sm mt-1">Exploits légendaires accomplis</p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-3xl font-black text-amber-400 font-mono">
                                                {{ Math.round(currentCollection.achievements.completed / (currentCollection.achievements.total || 1) * 100) }}%
                                            </div>
                                            <div class="text-xs text-slate-500 font-mono uppercase font-bold">
                                                {{ currentCollection.achievements.completed }} / {{ currentCollection.achievements.total }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="h-3 bg-slate-900 rounded-full overflow-hidden border border-white/5">
                                        <div class="h-full bg-gradient-to-r from-amber-700 via-amber-500 to-amber-400 transition-all duration-1000 relative shadow-[0_0_15px_rgba(245,158,11,0.3)]" :style="{ width: (currentCollection.achievements.total > 0 ? (currentCollection.achievements.completed / currentCollection.achievements.total * 100) : 0) + '%' }">
                                            <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Categories Drill-down -->
                            <section v-if="currentCollection.achievements.categories?.length">
                                <div class="flex justify-between items-center mb-6">
                                    <h4 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                                        Catégories de hauts-faits
                                        <div class="flex-1 h-px bg-slate-800"></div>
                                    </h4>
                                    <div v-if="totalPagesCategories > 1" class="flex items-center gap-2 ml-4">
                                        <button @click="pageCategories--" :disabled="pageCategories === 1" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                                            <span class="text-xs text-slate-300">&larr;</span>
                                        </button>
                                        <span class="text-[10px] font-mono text-slate-500">{{ pageCategories }} / {{ totalPagesCategories }}</span>
                                        <button @click="pageCategories++" :disabled="pageCategories === totalPagesCategories" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                                            <span class="text-xs text-slate-300">&rarr;</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-start">
                                    <div
                                        v-for="cat in paginatedCategories"
                                        :key="cat.name"
                                        @click="toggleCategory(cat)"
                                        class="bg-slate-900/40 border border-white/5 p-4 rounded-2xl hover:bg-slate-800/60 transition-colors group cursor-pointer"
                                    >
                                        <div class="flex justify-between items-start mb-3">
                                            <span class="text-sm font-bold text-slate-300 group-hover:text-amber-400 transition-colors">{{ cat.name }}</span>
                                            <span class="text-[10px] font-mono text-slate-500">{{ cat.completed }}/{{ cat.total }}</span>
                                        </div>
                                        <div class="h-1 bg-slate-950 rounded-full overflow-hidden">
                                            <div class="h-full bg-amber-600/80 transition-all duration-700" :style="{ width: (cat.completed / cat.total * 100) + '%' }"></div>
                                        </div>
                                        <div v-if="expandedCategory === cat.name" class="mt-4 pt-4 border-t border-white/5 space-y-1 max-h-48 overflow-y-auto no-scrollbar animate-in slide-in-from-top-2 duration-300">
                                            <div v-for="item in sortedItems(cat.items)" :key="item.id" class="flex justify-between items-center text-[10px] py-1">
                                                <a :href="`https://www.wowhead.com/fr/achievement=${item.id}`" target="_blank" rel="noopener" @click.stop :class="[item.is_completed ? 'text-amber-400 font-medium' : 'text-slate-500', 'hover:underline']">{{ item.name }}</a>
                                                <span v-if="item.is_completed" class="text-green-500 font-bold">&check;</span>
                                                <span v-else class="text-slate-800">&cir;</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- ============ MONTURES TAB ============ -->
                    <div v-if="activeTab === 'mounts'" class="space-y-6">
                        <div class="card-glass rounded-3xl border p-8 relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-amber-600/5 blur-3xl -mr-16 -mt-16"></div>
                            <div class="relative z-10 flex justify-between items-end mb-6">
                                <div>
                                    <h3 class="text-2xl font-black text-white flex items-center gap-3">
                                        <div class="w-2 h-8 bg-amber-500 rounded-full shadow-lg shadow-amber-500/50"></div>
                                        Montures
                                    </h3>
                                    <p class="text-slate-500 text-sm mt-1">Collection de montures du personnage</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-black text-amber-400 font-mono">{{ store.character.mountsCount }}</div>
                                    <div class="text-xs text-slate-500 font-mono uppercase font-bold">/ {{ store.character.mounts?.length || 0 }} total</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between items-center">
                            <h4 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                                Détail des Montures
                                <div class="flex-1 h-px bg-amber-500/10"></div>
                            </h4>
                            <div v-if="totalPagesMounts > 1" class="flex items-center gap-2 ml-4">
                                <button @click="pageMounts--" :disabled="pageMounts === 1" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                                    <span class="text-xs text-slate-300">&larr;</span>
                                </button>
                                <span class="text-[10px] font-mono text-slate-500">{{ pageMounts }} / {{ totalPagesMounts }}</span>
                                <button @click="pageMounts++" :disabled="pageMounts === totalPagesMounts" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                                    <span class="text-xs text-slate-300">&rarr;</span>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <a v-for="mount in paginatedMounts" :key="mount.id" :href="`https://www.wowhead.com/fr/mount=${mount.id}`" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 rounded-xl bg-slate-900/40 border border-white/5 group hover:border-amber-500/30 transition-all">
                                <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-amber-500 font-bold border border-white/10 group-hover:scale-110 transition-transform shadow-lg shadow-amber-500/5">
                                    M
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-slate-200 group-hover:text-amber-400 transition-colors">{{ mount.name }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">ID: {{ mount.id }}</div>
                                </div>
                                <div v-if="mount.is_completed" class="px-2 py-0.5 rounded text-[8px] font-black uppercase bg-green-500/10 text-green-400 border border-green-500/20">
                                    Obtenue
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- ============ MASCOTTES TAB ============ -->
                    <div v-if="activeTab === 'pets'" class="space-y-6">
                        <div class="card-glass rounded-3xl border p-8 relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/5 blur-3xl -mr-16 -mt-16"></div>
                            <div class="relative z-10 flex justify-between items-end mb-6">
                                <div>
                                    <h3 class="text-2xl font-black text-white flex items-center gap-3">
                                        <div class="w-2 h-8 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50"></div>
                                        Mascottes
                                    </h3>
                                    <p class="text-slate-500 text-sm mt-1">Collection de mascottes du personnage</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-black text-blue-400 font-mono">{{ store.character.petsCount }}</div>
                                    <div class="text-xs text-slate-500 font-mono uppercase font-bold">/ {{ store.character.pets?.length || 0 }} total</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between items-center">
                            <h4 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] flex items-center gap-4 flex-1">
                                Détail des Mascottes
                                <div class="flex-1 h-px bg-blue-500/10"></div>
                            </h4>
                            <div v-if="totalPagesPets > 1" class="flex items-center gap-2 ml-4">
                                <button @click="pagePets--" :disabled="pagePets === 1" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                                    <span class="text-xs text-slate-300">&larr;</span>
                                </button>
                                <span class="text-[10px] font-mono text-slate-500">{{ pagePets }} / {{ totalPagesPets }}</span>
                                <button @click="pagePets++" :disabled="pagePets === totalPagesPets" class="w-8 h-8 rounded-lg border border-white/5 flex items-center justify-center hover:bg-slate-800 disabled:opacity-30 transition-colors">
                                    <span class="text-xs text-slate-300">&rarr;</span>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <a v-for="pet in paginatedPets" :key="pet.id" :href="`https://www.wowhead.com/fr/pet=${pet.id}`" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 rounded-xl bg-slate-900/40 border border-white/5 group hover:border-blue-500/30 transition-all">
                                <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-blue-500 font-bold border border-white/10 group-hover:scale-110 transition-transform shadow-lg shadow-blue-500/5">
                                    P
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-slate-200 group-hover:text-blue-400 transition-colors">{{ pet.name }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">ID: {{ pet.id }}</div>
                                </div>
                                <div v-if="pet.is_completed" class="px-2 py-0.5 rounded text-[8px] font-black uppercase bg-green-500/10 text-green-400 border border-green-500/20">
                                    Obtenue
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ MY CHARACTERS VIEW ============ -->
            <div v-else-if="store.currentView === 'my-characters'" class="space-y-8 animate-in fade-in duration-500">
                <div class="text-center max-w-3xl mx-auto">
                    <h2 class="text-3xl md:text-4xl font-black mb-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-200 via-blue-400 to-blue-600">Mes personnages</span>
                    </h2>
                    <p class="text-slate-400 text-sm">Cliquez sur un personnage pour voir sa progression</p>
                </div>

                <!-- Loading -->
                <div v-if="store.loadingCharacters" class="card-glass rounded-2xl p-8 md:p-10 max-w-2xl mx-auto">
                    <div class="text-center">
                        <div class="relative inline-block mb-6">
                            <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-blue-500 border-r-purple-500 animate-spin w-24 h-24"></div>
                            <div class="absolute inset-0 m-2 rounded-full border-4 border-transparent border-b-purple-500 border-l-blue-500 animate-spin-reverse w-20 h-20"></div>
                            <div class="relative flex items-center justify-center w-24 h-24">
                                <span class="text-4xl animate-pulse">&#128100;</span>
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold mb-3 text-white">Chargement en cours...</h3>
                        <p class="text-slate-400 mb-4">R&eacute;cup&eacute;ration de vos personnages</p>
                        <div class="flex justify-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                            <div class="w-3 h-3 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                            <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                        </div>
                        <p class="text-xs mt-6 text-slate-600">Cela peut prendre quelques instants selon le nombre de personnages...</p>
                    </div>
                </div>

                <template v-else-if="store.userCharacters.length">
                    <!-- Search -->
                    <div class="max-w-md mx-auto">
                        <input
                            v-model="characterSearch"
                            type="text"
                            placeholder="Rechercher un personnage..."
                            class="w-full bg-slate-800/80 border border-white/5 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm placeholder-slate-500 transition-all"
                        >
                    </div>

                    <!-- Character Grid -->
                    <div v-if="filteredUserCharacters.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <button
                            v-for="char in filteredUserCharacters"
                            :key="char.name + '-' + char.realmSlug"
                            @click="selectCharacter(char)"
                            class="bg-slate-900/40 border border-white/5 p-5 rounded-2xl hover:bg-slate-800/60 hover:border-blue-500/20 transition-all group text-left cursor-pointer"
                        >
                            <div class="flex items-center gap-4">
                                <img
                                    v-if="char.avatarUrl"
                                    :src="char.avatarUrl"
                                    :alt="char.name"
                                    class="w-12 h-12 rounded-xl border border-white/10 shadow-lg bg-slate-800 object-cover"
                                    :style="{ borderColor: (charClassColors[char.classId] || '#FFFFFF') + '30' }"
                                >
                                <div
                                    v-else
                                    class="w-12 h-12 rounded-xl flex items-center justify-center text-lg font-black border border-white/10 shadow-lg"
                                    :style="{ backgroundColor: (charClassColors[char.classId] || '#FFFFFF') + '15', color: charClassColors[char.classId] || '#FFFFFF', borderColor: (charClassColors[char.classId] || '#FFFFFF') + '30' }"
                                >
                                    {{ char.name.charAt(0) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-base font-bold truncate group-hover:text-blue-400 transition-colors" :style="{ color: charClassColors[char.classId] || '#FFFFFF' }">
                                        {{ char.name }}
                                    </div>
                                    <div class="text-xs text-slate-500 truncate">{{ char.realm }}</div>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-3 text-[11px] text-slate-400">
                                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5 font-mono">Niv {{ char.level }}</span>
                                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5">{{ char.raceName }}</span>
                                <span class="px-2 py-0.5 bg-slate-800 rounded border border-white/5" :style="{ color: charClassColors[char.classId] || '#FFFFFF' }">{{ char.className }}</span>
                            </div>
                        </button>
                    </div>

                    <!-- No search results -->
                    <div v-else class="text-center py-16">
                        <p class="text-slate-500">Aucun personnage ne correspond &agrave; votre recherche.</p>
                    </div>
                </template>

                <!-- Empty state -->
                <div v-else class="text-center py-16">
                    <p class="text-slate-500">Aucun personnage trouv&eacute;.</p>
                </div>
            </div>

            <!-- ============ HOME VIEW ============ -->
            <div v-else-if="store.currentView === 'home'" class="space-y-16 py-8">
                <!-- Hero -->
                <div class="text-center max-w-3xl mx-auto">
                    <div class="w-20 h-20 mx-auto bg-slate-900 rounded-3xl border border-white/10 flex items-center justify-center mb-8 shadow-2xl shadow-blue-500/10">
                        <img src="/images/logo.png" alt="" class="w-14 h-14 rounded-xl object-cover">
                    </div>
                    <h2 class="text-4xl md:text-5xl font-black mb-4">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-200 via-blue-400 to-blue-600">Suivez votre progression</span>
                    </h2>
                    <p class="text-lg text-slate-400 leading-relaxed max-w-2xl mx-auto">
                        WowPlanet analyse votre personnage World of Warcraft via l'API Blizzard et compare vos accomplissements avec la base de donn&eacute;es compl&egrave;te du jeu. Quêtes, hauts-faits, montures, mascottes : visualisez tout ce qu'il vous reste &agrave; accomplir.
                    </p>
                </div>

                <!-- Battle.net CTA -->
                <div class="max-w-xl mx-auto text-center">
                    <div class="card-glass rounded-2xl border p-8">
                        <template v-if="store.isAuthenticated">
                            <h3 class="text-xl font-bold text-white mb-2">Bienvenue !</h3>
                            <p class="text-slate-400 text-sm mb-6">Vous &ecirc;tes connect&eacute; avec Battle.net. Acc&eacute;dez directement &agrave; tous vos personnages.</p>
                            <button
                                @click="showMyCharacters"
                                class="btn-gradient text-white font-semibold px-6 py-2.5 rounded-lg text-sm shadow-lg shadow-blue-500/20"
                            >
                                Voir mes personnages
                            </button>
                        </template>
                        <template v-else>
                            <h3 class="text-xl font-bold text-white mb-2">Commencer</h3>
                            <p class="text-slate-400 text-sm mb-6">Connectez-vous avec Battle.net pour acc&eacute;der &agrave; tous vos personnages, ou recherchez un personnage manuellement.</p>
                            <div class="flex flex-col items-center gap-4">
                                <a
                                    href="/auth/blizzard/redirect"
                                    class="btn-gradient text-white font-semibold px-6 py-2.5 rounded-lg text-sm shadow-lg shadow-blue-500/20 inline-block"
                                >
                                    Se connecter avec Battle.net
                                </a>
                                <div class="flex items-center gap-3 text-slate-600 text-xs">
                                    <div class="h-px w-12 bg-slate-800"></div>
                                    ou
                                    <div class="h-px w-12 bg-slate-800"></div>
                                </div>
                                <div class="flex items-center justify-center gap-3 text-slate-500 text-sm">
                                    <span class="px-3 py-1 bg-slate-800 rounded-lg border border-white/5 font-mono text-xs">Royaume</span>
                                    <span>+</span>
                                    <span class="px-3 py-1 bg-slate-800 rounded-lg border border-white/5 font-mono text-xs">Nom du personnage</span>
                                    <span>&#8594;</span>
                                    <span class="px-3 py-1 bg-blue-600/20 text-blue-400 rounded-lg border border-blue-500/20 font-mono text-xs">Rechercher</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Features -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
                    <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-6 text-center">
                        <div class="w-12 h-12 mx-auto bg-blue-600/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-blue-400 text-xl font-bold mb-4">Q</div>
                        <h4 class="font-bold text-white mb-1">Qu&ecirc;tes</h4>
                        <p class="text-xs text-slate-500">Progression par zone et par extension, avec plus de 21 000 qu&ecirc;tes r&eacute;f&eacute;renc&eacute;es.</p>
                    </div>
                    <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-6 text-center">
                        <div class="w-12 h-12 mx-auto bg-amber-600/10 border border-amber-500/20 rounded-xl flex items-center justify-center text-amber-400 text-xl font-bold mb-4">HF</div>
                        <h4 class="font-bold text-white mb-1">Hauts-faits</h4>
                        <p class="text-xs text-slate-500">Plus de 8 600 hauts-faits tri&eacute;s par cat&eacute;gorie et par extension.</p>
                    </div>
                    <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-6 text-center">
                        <div class="w-12 h-12 mx-auto bg-amber-600/10 border border-amber-500/20 rounded-xl flex items-center justify-center text-amber-400 text-xl font-bold mb-4">M</div>
                        <h4 class="font-bold text-white mb-1">Montures</h4>
                        <p class="text-xs text-slate-500">1 569 montures avec statut d'obtention et lien Wowhead.</p>
                    </div>
                    <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-6 text-center">
                        <div class="w-12 h-12 mx-auto bg-blue-600/10 border border-blue-500/20 rounded-xl flex items-center justify-center text-blue-400 text-xl font-bold mb-4">P</div>
                        <h4 class="font-bold text-white mb-1">Mascottes</h4>
                        <p class="text-xs text-slate-500">2 117 mascottes de combat avec suivi de collection.</p>
                    </div>
                </div>

                <!-- Data source info -->
                <div class="text-center text-xs text-slate-600 max-w-lg mx-auto">
                    Donn&eacute;es synchronis&eacute;es depuis l'API officielle Blizzard. Tous les noms sont en fran&ccedil;ais.
                    <br>Chaque &eacute;l&eacute;ment est li&eacute; &agrave; sa fiche Wowhead pour plus de d&eacute;tails.
                </div>
            </div>

        </main>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useCharacterStore } from '../stores/character';

const store = useCharacterStore();
const activeTab = ref('quests');
const activeExpansion = ref(10);
const search = ref({
    realm: 'Dalaran',
    name: ''
});

// Check auth on mount
onMounted(() => {
    store.checkAuth();
});

const contentTabs = computed(() => [
    { id: 'quests', label: 'Quêtes', count: undefined },
    { id: 'achievements', label: 'Hauts-faits', count: undefined },
    { id: 'mounts', label: 'Montures', count: store.character?.mountsCount },
    { id: 'pets', label: 'Mascottes', count: store.character?.petsCount },
]);

// WoW class colors (by class ID)
const classColors = {
    1: '#C79C6E',  // Warrior
    2: '#F58CBA',  // Paladin
    3: '#ABD473',  // Hunter
    4: '#FFF569',  // Rogue
    5: '#FFFFFF',  // Priest
    6: '#C41E3A',  // Death Knight
    7: '#0070DE',  // Shaman
    8: '#69CCF0',  // Mage
    9: '#9482C9',  // Warlock
    10: '#00FF96', // Monk
    11: '#FF7D0A', // Druid
    12: '#A330C9', // Demon Hunter
    13: '#33937F', // Evoker
};

// Used for character detail view
const classColor = computed(() => {
    return classColors[store.character?.classId] || '#FFFFFF';
});

// Same map exposed for "my characters" grid
const charClassColors = classColors;

// My characters search
const characterSearch = ref('');

const filteredUserCharacters = computed(() => {
    const q = characterSearch.value.toLowerCase().trim();
    if (!q) return store.userCharacters;
    return store.userCharacters.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.realm.toLowerCase().includes(q) ||
        c.className.toLowerCase().includes(q) ||
        c.raceName.toLowerCase().includes(q) ||
        c.faction.toLowerCase().includes(q)
    );
});

// Drill-down state
const expandedZone = ref(null);
const expandedCategory = ref(null);

const toggleZone = (zone) => {
    expandedZone.value = expandedZone.value === zone.name ? null : zone.name;
};

const toggleCategory = (cat) => {
    expandedCategory.value = expandedCategory.value === cat.name ? null : cat.name;
};

// Pagination
const itemsPerPage = 8;
const itemsPerCollectionPage = 24;
const pageZones = ref(1);
const pageCategories = ref(1);
const pageMounts = ref(1);
const pagePets = ref(1);

const searchCharacter = () => {
    if (search.value.name && search.value.realm) {
        store.fetchCharacter(search.value.realm, search.value.name);
    }
};

const goHome = () => {
    store.goHome();
    search.value.name = '';
    activeTab.value = 'quests';
};

const showMyCharacters = () => {
    store.fetchUserCharacters();
};

const selectCharacter = (char) => {
    search.value.realm = char.realmSlug;
    search.value.name = char.name;
    store.fetchCharacter(char.realmSlug, char.name);
};

// Sort helper for items within zones/categories
const sortedItems = (items) => {
    return [...items].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
};

const currentCollection = computed(() => {
    return store.character?.collections?.[activeExpansion.value] || null;
});

// Sorted + paginated zones
const sortedZones = computed(() => {
    if (!currentCollection.value) return [];
    const zones = currentCollection.value.quests.zones || [];
    return [...zones].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const paginatedZones = computed(() => {
    const start = (pageZones.value - 1) * itemsPerPage;
    return sortedZones.value.slice(start, start + itemsPerPage);
});

const totalPagesZones = computed(() => {
    return Math.ceil(sortedZones.value.length / itemsPerPage);
});

// Sorted + paginated categories
const sortedCategories = computed(() => {
    if (!currentCollection.value) return [];
    const categories = currentCollection.value.achievements.categories || [];
    return [...categories].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const paginatedCategories = computed(() => {
    const start = (pageCategories.value - 1) * itemsPerPage;
    return sortedCategories.value.slice(start, start + itemsPerPage);
});

const totalPagesCategories = computed(() => {
    return Math.ceil(sortedCategories.value.length / itemsPerPage);
});

// Sorted + paginated mounts
const sortedMounts = computed(() => {
    if (!store.character) return [];
    const mounts = store.character.mounts || [];
    return [...mounts].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const paginatedMounts = computed(() => {
    const start = (pageMounts.value - 1) * itemsPerCollectionPage;
    return sortedMounts.value.slice(start, start + itemsPerCollectionPage);
});

const totalPagesMounts = computed(() => {
    return Math.ceil(sortedMounts.value.length / itemsPerCollectionPage);
});

// Sorted + paginated pets
const sortedPets = computed(() => {
    if (!store.character) return [];
    const pets = store.character.pets || [];
    return [...pets].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
});

const paginatedPets = computed(() => {
    const start = (pagePets.value - 1) * itemsPerCollectionPage;
    return sortedPets.value.slice(start, start + itemsPerCollectionPage);
});

const totalPagesPets = computed(() => {
    return Math.ceil(sortedPets.value.length / itemsPerCollectionPage);
});

// Reset pages when expansion changes
watch(activeExpansion, () => {
    pageZones.value = 1;
    pageCategories.value = 1;
    expandedZone.value = null;
    expandedCategory.value = null;
});

// Reset pages when tab changes
watch(activeTab, () => {
    pageMounts.value = 1;
    pagePets.value = 1;
    pageZones.value = 1;
    pageCategories.value = 1;
    expandedZone.value = null;
    expandedCategory.value = null;
});
</script>

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

@keyframes spin-reverse {
    from { transform: rotate(360deg); }
    to { transform: rotate(0deg); }
}
.animate-spin-reverse {
    animation: spin-reverse 1.5s linear infinite;
}
</style>
