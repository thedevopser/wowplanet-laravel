# Utilitaires JavaScript

Fonctions pures dans `resources/js/utils/`. Aucune dépendance vers Vue ou les stores.

---

## `scoreCalculator.js`

Calcule le score de progression d'un personnage ou d'un compte (0–100).

### Constantes exportées

**`WEIGHTS`** — Pondération de chaque dimension dans le score global :

| Dimension | Poids |
|---|---|
| `quests` | 15 % |
| `achievements` | 25 % |
| `reputations` | 15 % |
| `mounts` | 15 % |
| `pets` | 10 % |
| `decor` | 10 % |
| `professions` | 10 % |

**`DIMENSION_LABELS`** — Noms français des dimensions (ex. : `quests → 'Quêtes'`)

**`DIMENSION_COLORS`** — Couleurs hex par dimension (utilisées dans les graphiques)

### Fonctions exportées

**`computeScore(character)`**

Calcule le score d'un objet personnage ou profil virtuel de compte.

```js
const result = computeScore(character);
// result = {
//   global: 42.3,                 // Score global arrondi à 1 décimale
//   dimensions: {
//     quests:       { completed, total, score },
//     achievements: { completed, total, score },
//     reputations:  { completed, total, score },
//     mounts:       { completed, total, score },
//     pets:         { completed, total, score },
//     decor:        { completed, total, score },
//     professions:  { completed, total, score },
//   }
// }
```

Retourne `null` si `character` est falsy.

**`getScoreColor(score)`** → `string` (hex)
Couleur selon le score : vert ≥75, jaune ≥50, orange ≥25, rouge <25.

**`getRankColorHex(score)`** → `string` (hex)
Couleur de rang : orange ≥90 (Légendaire), violet ≥75, bleu ≥50, vert ≥25, gris <25.

**`getScoreTailwindColor(score)`** → `string`
Classe Tailwind correspondante : `text-green-400`, `text-yellow-400`, `text-orange-400` ou `text-red-400`.

---

## `accountScoreAggregator.js`

Agrège plusieurs profils de personnages côté client pour calculer le score de compte en temps réel (page "Mon Score").

### `createAccountAggregator()`

Factory qui retourne un agrégateur avec état interne.

```js
const aggregator = createAccountAggregator();

// Fusionner chaque personnage au fur et à mesure
aggregator.mergeCharacter(characterData);

// Lire les résultats
const score = aggregator.getScore();        // { global, dimensions }
const profile = aggregator.getVirtualProfile(); // Profil virtuel compte
const count = aggregator.getLoadedCount();  // Nombre de personnages fusionnés
```

**Logique de fusion**

| Donnée | Stratégie |
|---|---|
| Montures / Mascottes / Décorations | Compte-wide : prise depuis le 1er personnage |
| Quêtes / Hauts-faits | Union de tous les IDs complétés |
| Réputations | Meilleur nombre complété par extension |
| Professions | Union des recettes ; meilleur ratio pour le score |

---

## `classColors.js`

Tableau des couleurs de classes WoW (couleurs officielles Blizzard).

```js
export const classColors = {
    1: '#C79C6E',  // Guerrier
    2: '#F58CBA',  // Paladin
    3: '#ABD473',  // Chasseur
    4: '#FFF569',  // Voleur
    5: '#FFFFFF',  // Prêtre
    6: '#C41E3A',  // Chevalier de la Mort
    7: '#0070DE',  // Chaman
    8: '#69CCF0',  // Mage
    9: '#9482C9',  // Démoniste
    10: '#00FF96', // Moine
    11: '#FF7D0A', // Druide
    12: '#A330C9', // Chasseur de démons
    13: '#33937F', // Évocateur
};
```

---

## `scoreCardRenderer.js`

Génère une carte de score visuelle sur un `<canvas>` HTML5. Utilisé pour partager son score en image.

### `renderScoreCard(options)`

Dessine une carte 700×430 px et retourne l'élément `<canvas>`.

**Paramètres**

| Paramètre | Type | Description |
|---|---|---|
| `variant` | `'personal'\|'account'` | Personnage unique ou score compte |
| `characterName` | `string` | Nom du personnage |
| `characterRealm` | `string` | Royaume |
| `characterClass` | `string` | Classe |
| `characterRace` | `string` | Race |
| `characterLevel` | `number` | Niveau |
| `classId` | `number` | ID de classe (pour la couleur du nom) |
| `characterCount` | `number` | Nombre de personnages (mode `account`) |
| `globalScore` | `number` | Score global (0–100) |
| `rank` | `string` | Libellé du rang |
| `dimensions` | `object` | Scores par dimension (depuis `computeScore`) |

**Structure de la carte**
- En-tête : logo WowPlanet + infos personnage/compte
- Score global en grand avec badge de rang coloré
- 7 barres de progression une par dimension avec pourcentage et ratio
- Pied de page : `wowplanet.fr`
