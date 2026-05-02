# Pages et Composants Vue

---

## Configuration (`app.js`, `router.js`, `bootstrap.js`)

**`app.js`** — Point d'entrée Vue. Configure `window.whTooltips` pour les infobulles Wowhead (locale `fr`), crée l'application avec Pinia et Vue Router, monte sur `#app`.

**`bootstrap.js`** — Configure Axios avec l'en-tête `X-Requested-With: XMLHttpRequest` (requis par Laravel pour détecter les requêtes AJAX).

**`router.js`** — Vue Router en mode history. Après chaque navigation : met à jour le `document.title` et rafraîchit les liens Wowhead.

---

## Pages (`resources/js/pages/`)

Chaque page correspond à une route Vue Router. Elles consomment les stores et appellent les API via Axios.

| Page | Route | Description |
|---|---|---|
| `HomePage.vue` | `/` | Page d'accueil avec barre de recherche de personnage. |
| `CharacterPage.vue` | `/character/:realm/:name` | Profil complet d'un personnage avec tous les onglets. |
| `MyCharactersPage.vue` | `/my-characters` | Liste des personnages du compte connecté. |
| `ClassStatsPage.vue` | `/class-stats` | Statistiques globales par classe. |
| `AccountScorePage.vue` | `/my-score` | Score de compte multi-personnages avec graphiques. |
| `DatabaseIndexPage.vue` | `/base-de-donnees` | Page d'accueil de la base de données WoW. |
| `DatabaseMountsPage.vue` | `/base-de-donnees/montures/:category?` | Liste des montures avec filtre par catégorie. |
| `DatabaseAchievementsPage.vue` | `/base-de-donnees/hauts-faits/:expansion?` | Hauts-faits filtrés par extension. |
| `DatabaseQuestsPage.vue` | `/base-de-donnees/quetes/:expansion?` | Quêtes filtrées par extension. |
| `DatabasePetsPage.vue` | `/base-de-donnees/mascottes/:category?` | Mascottes filtrées par catégorie. |
| `DatabaseDecorsPage.vue` | `/base-de-donnees/decorations/:category?` | Décorations filtrées par catégorie. |
| `DatabaseProfessionsPage.vue` | `/base-de-donnees/professions/:profession?` | Professions et recettes. |
| `FaqPage.vue` | `/faq` | Questions fréquentes. |
| `PrivacyPage.vue` | `/privacy` | Politique de confidentialité. |
| `CguPage.vue` | `/cgu` | Conditions générales d'utilisation. |
| `AdminPage.vue` | `/admin` | Interface d'administration (imports, cache, maintenance). |
| `NotFoundPage.vue` | `/:pathMatch(.*)` | Page 404. |

---

## Composants (`resources/js/components/`)

### Composants structurels

| Composant | Description |
|---|---|
| `App.vue` | Racine de l'application. Gère le thème global (dark/light) et vérifie l'authentification au montage. |
| `AppHeader.vue` | En-tête avec navigation principale, bouton de connexion Battle.net et bouton de thème. |
| `AppFooter.vue` | Pied de page. |
| `DatabaseLayout.vue` | Layout avec sidebar pour les pages de base de données (`/base-de-donnees/*`). |
| `DatabasePageHeader.vue` | En-tête commun aux pages de base de données (titre + fil d'Ariane). |
| `DatabasePagination.vue` | Composant de pagination réutilisable pour les listes de la base de données. |
| `BreadcrumbNav.vue` | Fil d'Ariane générique. |

### Onglets de la page personnage

Chaque onglet correspond à une section du profil. Ils reçoivent les données du store `character` et affichent la progression.

| Composant | Section |
|---|---|
| `QuestsTab.vue` | Progression des quêtes par extension et zone. |
| `AchievementsTab.vue` | Progression des hauts-faits par extension et catégorie. |
| `ReputationsTab.vue` | Progression des réputations par extension. |
| `MountsTab.vue` | Collection de montures avec filtre. |
| `PetsTab.vue` | Collection de mascottes avec filtre. |
| `DecorTab.vue` | Collection de décorations avec filtre. |
| `ProfessionsTab.vue` | Progression des métiers par extension avec recettes. |
| `EquipmentTab.vue` | Équipement actuel avec niveau d'objet par emplacement. |
| `MythicPlusTab.vue` | Progression Clé mythique+ de la saison courante. |
| `ScoreTab.vue` | Score de progression individuel avec radar chart. |
| `TalentTreeSection.vue` | Section de l'arbre de talents. |
| `TalentTreeGrid.vue` | Grille de l'arbre de talents complet. |
| `TalentNode.vue` | Nœud individuel dans l'arbre de talents. |

### Composants réutilisables

| Composant | Description |
|---|---|
| `CollectionIcon.vue` | Icône d'un élément de collection (monture, mascotte, etc.) avec infobulle Wowhead. |
| `CategoryIcon.vue` | Icône d'une catégorie de collection. |
| `CharacterCard.vue` | Carte de résumé d'un personnage (avatar, nom, classe, niveau). |
| `ExpansionSelector.vue` | Sélecteur d'extension pour filtrer les données. |
| `SearchFilter.vue` | Champ de recherche avec filtre temps réel. |
| `ScoreBadge.vue` | Badge coloré affichant un score et un rang. |
| `ScoreRadar.vue` | Graphique radar des 7 dimensions de progression (canvas). |
| `ShareScoreModal.vue` | Modale de partage du score (génère une image via `scoreCardRenderer`). |
| `LoadingSpinner.vue` | Indicateur de chargement. |
| `SkeletonLoader.vue` | Squelette de chargement (placeholder animé). |
| `TaskSidebar.vue` | Sidebar des tâches récurrentes (quotidien/hebdo/mensuel). |
