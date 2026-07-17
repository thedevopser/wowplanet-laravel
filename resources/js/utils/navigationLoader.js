// Overlay de chargement affiché pendant une navigation par rechargement complet
// (ex. SPA legacy -> page personnage Inertia servie en SSR). Injecté directement
// dans le DOM pour survivre jusqu'à ce que le document suivant remplace la page.
//
// Reproduit le visuel du composant LoadingSpinner.vue (aucune régression) :
// les classes Tailwind et `card-glass` sont déjà chargées sur la page legacy ;
// seul le sens de rotation inverse est inliné pour rester autonome.

const OVERLAY_ID = 'wp-nav-loader';

export function showNavigationLoader(hint = 'Quêtes, hauts-faits, métiers, montures, mascottes...') {
    if (typeof document === 'undefined' || document.getElementById(OVERLAY_ID)) return;

    const overlay = document.createElement('div');
    overlay.id = OVERLAY_ID;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.style.cssText = [
        'position:fixed', 'inset:0', 'z-index:9999',
        'display:flex', 'align-items:center', 'justify-content:center',
        'padding:1.5rem',
        'background:rgba(15,23,42,0.88)', 'backdrop-filter:blur(4px)',
    ].join(';');

    overlay.innerHTML = `
        <style>
            @keyframes wp-spin-reverse { from { transform: rotate(360deg); } to { transform: rotate(0deg); } }
            #${OVERLAY_ID} .wp-spin-reverse { animation: wp-spin-reverse 1.5s linear infinite; }
        </style>
        <div class="card-glass rounded-2xl p-8 md:p-10 max-w-2xl mx-auto w-full">
            <div class="text-center">
                <div class="relative inline-block mb-6">
                    <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-blue-500 border-r-purple-500 animate-spin w-24 h-24"></div>
                    <div class="absolute inset-0 m-2 rounded-full border-4 border-transparent border-b-purple-500 border-l-blue-500 wp-spin-reverse w-20 h-20"></div>
                    <div class="relative flex items-center justify-center w-24 h-24">
                        <span class="text-4xl animate-pulse">⚔</span>
                    </div>
                </div>
                <h3 class="text-2xl font-bold mb-3 text-white">Synchronisation en cours...</h3>
                <p class="text-slate-400 mb-4">Analyse de votre personnage via l'API Blizzard</p>
                <div class="flex justify-center gap-2">
                    <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                    <div class="w-3 h-3 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                    <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                </div>
                <p class="text-xs mt-6 text-slate-600">${hint}</p>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
}
