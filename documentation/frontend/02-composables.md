# Composables Vue

Fonctions de composition réutilisables dans `resources/js/composables/`.

---

## `useWowheadTooltips` (`composables/useWowheadTooltips.js`)

Active les infobulles Wowhead sur les liens détectés dans le DOM. L'intégration utilise `window.$WowheadPower` injecté par le script externe Wowhead (`whTooltips` configuré dans `app.js`).

**Utilisation**

```js
import { useWowheadTooltips } from '../composables/useWowheadTooltips';

// Dans setup()
const { refreshWowheadLinks } = useWowheadTooltips();
```

**Comportement**

- Appelle automatiquement `$WowheadPower.refreshLinks()` après `onMounted` et `onUpdated` (via `nextTick`).
- Exposé `refreshWowheadLinks()` pour un rafraîchissement manuel si nécessaire.

> Ce composable est nécessaire dans tout composant qui affiche des liens Wowhead (hauts-faits, sorts, objets) car le DOM Vue est rendu après l'initialisation du script Wowhead.
