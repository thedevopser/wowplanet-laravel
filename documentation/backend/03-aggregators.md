# Agrégateurs de progression

Situés dans `app/Application/Services/Progress/`, ces classes transforment les données brutes de l'API Blizzard en structures de progression prêtes à consommer par le frontend. Chacun est spécialisé sur un type de contenu.

---

## `AchievementProgressAggregator`

Construit l'arbre de progression des hauts-faits par extension et catégorie.

**Méthode principale**

```
aggregate(array $completedAchievementIds): array<int, array{
    total: int,
    completed: int,
    categories: list<array<string, mixed>>
}>
```

Prend la liste des IDs de hauts-faits complétés par le personnage. Retourne un tableau indexé par `expansion_id` contenant le total, le complété, et la liste détaillée par catégorie.

---

## `CollectionProgressAggregator`

Croise les listes complètes de montures/mascottes/décorations de la base avec ce que le personnage possède.

**Méthodes**

| Méthode | Paramètres | Description |
|---|---|---|
| `aggregateMounts` | `array $characterMountIds` | Retourne toutes les montures actives avec `is_completed` selon la collection du personnage. Inclut `source`, `category`, `wowhead_id`, `icon_url`. |
| `aggregatePets` | `array $characterPetIds` | Idem pour les mascottes. |
| `aggregateDecor` | `array $characterDecorIds` | Idem pour les décorations. |

---

## `EquipmentAggregator`

Transforme la réponse brute d'équipement de l'API Blizzard en liste d'emplacements avec noms traduits, niveau d'objet et icônes.

**Méthode principale**

```
aggregate(array $apiResponse, array $iconMap = []): list<array{
    slot: string,
    slot_name: string,
    item_id: int,
    name: string,
    item_level: int,
    quality: string,
    icon_url: string|null
}>
```

`$iconMap` est un tableau `item_id → icon_url` pré-chargé pour éviter des appels API supplémentaires.

---

## `ProfessionProgressAggregator`

Calcule la progression de chaque métier par extension : points de compétence et recettes apprises.

**Constantes**

| Constante | Description |
|---|---|
| `PROFESSION_NAMES_FR` | Tableau de 14 professions avec leur nom français |
| `SECONDARY_PROFESSION_IDS` | `[185, 356, 794]` — IDs des métiers secondaires (Cuisine, Pêche, Premiers Secours) |

**Méthode principale**

```
aggregate(array $professionsResponse, string $characterFaction): list<array<string, mixed>>
```

Retourne la liste des métiers du personnage avec, pour chaque extension, le nombre de recettes connues/total et les points de compétence.

---

## `QuestProgressAggregator`

Construit la progression des quêtes par extension et zone.

**Méthode principale**

```
aggregate(array $completedQuestIds, string $faction): array<int, array{
    total: int,
    completed: int,
    zones: list<array<string, mixed>>
}>
```

Le paramètre `$faction` (`Alliance` ou `Horde`) filtre les quêtes de faction opposée. Retourne un tableau indexé par `expansion_id`.

---

## `ReputationProgressAggregator`

Agrège la progression de réputation par extension.

**Méthode principale**

```
aggregate(array $reputationsResponse, string $characterFaction = ''): array<int, array{
    total: int,
    completed: int,
    factions: list<array<string, mixed>>
}>
```

Une réputation est considérée "complétée" quand elle atteint Exalté (ou le niveau de renom maximum pour les systèmes de renom modernes). Les factions de la faction opposée au personnage sont exclues.

---

## `TalentAggregator`

Transforme les données brutes des spécialisations et de l'arbre de talents en structure utilisable par le composant `TalentTreeGrid.vue`.

**Méthode principale**

```
aggregate(array $specializationsResponse, array $talentTreeResponse): array<string, mixed>
```

Gère les talents de classe, de spécialisation et les arbres héroïques (Hero Tree, introduits dans The War Within). Retourne la spécialisation active, les nœuds sélectionnés et la structure complète de l'arbre.
