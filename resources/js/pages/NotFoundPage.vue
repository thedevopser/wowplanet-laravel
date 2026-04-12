<template>
    <div class="flex flex-col items-center justify-center w-full min-h-[60vh] py-12 text-center">
        <div class="text-8xl sm:text-9xl font-black text-slate-800 select-none">404</div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-300 mt-4 mb-2">Page introuvable</h1>
        <p class="text-slate-500 mb-8 max-w-md">La page que vous recherchez n&rsquo;existe pas ou a &eacute;t&eacute; d&eacute;plac&eacute;e.</p>
        <router-link
            to="/"
            class="btn-gradient text-white font-semibold px-6 py-2.5 rounded-lg text-sm shadow-lg shadow-blue-500/20 inline-block"
        >
            Retour &agrave; l&rsquo;accueil
        </router-link>
    </div>
</template>
