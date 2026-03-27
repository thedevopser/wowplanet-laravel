# SEO Real Audit & Improvement Plan - WowPlanet

## Date: 2026-03-27

## Context

WowPlanet is a Laravel 11 + Vue 3 SPA for tracking World of Warcraft progression in French. Previous SEO work focused on technical implementation (meta tags, sitemaps, JSON-LD, robots.txt). This audit reveals the **real SEO problems**: thin content, no keyword strategy, zero authority, and CTR issues.

### GSC Data Summary (3 months: Dec 26 2025 - Mar 25 2026)

- **26 total clicks, 71 impressions** (almost zero organic traffic)
- 98% of clicks come from brand query "wowplanet"
- 0 clicks on any generic WoW keyword
- Site effectively invisible until late February 2026 (very young domain)
- 9 pages indexed, some at position 1-2 but with 0 CTR on non-brand queries
- "monture wow" (target keyword) at position 50

### Current SEO Health Index: 52/100 (Poor)

| Category              | Score | Weight | Contribution |
|-----------------------|-------|--------|-------------|
| Crawlability          | 55    | 30     | 16.5        |
| Technical Foundations | 70    | 25     | 17.5        |
| On-Page Optimization  | 50    | 20     | 10.0        |
| Content & E-E-A-T     | 35    | 15     | 5.25        |
| Authority & Trust     | 25    | 10     | 2.5         |

---

## Architecture Decisions

### Rendering Strategy: Keep current approach

The `#seo-content` hidden div in `welcome.blade.php` is acceptable because:
- Content mirrors what the user sees (not cloaking)
- Vue 3 SPA reactivity must be preserved
- Inertia SSR migration is too costly for the gain
- Googlebot renders JavaScript (second-wave indexing) as backup

### Content Generation: Programmatic

Descriptive texts generated automatically from existing database fields (categories, sources, factions, expansion data) rather than manually written editorial content.

---

## Plan: Phase 1 - Quick Wins Techniques (Priority: HIGH)

### 1.1 HTTP 404 for invalid routes

**Problem**: All invalid URLs return HTTP 200 (homepage). Pollutes crawl budget.

**Files to modify**:
- `routes/web.php` - Add explicit 404 route handling
- `app/Http/Controllers/SeoController.php` - `spa()` method should check path validity
- `app/Http/Controllers/DatabaseController.php` - Return 404 for invalid slugs instead of fallback
- `app/Application/Services/SeoContentRenderer.php` - Stop silently falling back (lines 87-89, 253-255, 299-301, 354-356)
- `resources/js/router.js` - Change catch-all from redirect to 404 page
- New: `resources/js/pages/NotFoundPage.vue` - Styled 404 page

**Behavior**:
- Invalid database slugs (e.g., `/base-de-donnees/montures/nonexistent`) -> HTTP 404
- Unknown routes (e.g., `/random-page`) -> HTTP 404
- Vue router catch-all shows 404 page component instead of redirecting to home

### 1.2 Unique meta for FAQ/CGU/Privacy

**Problem**: `spa()` catch-all in `SeoController` uses `getHomeMeta()` for all SPA routes. FAQ, CGU, Privacy get homepage meta.

**Files to modify**:
- `routes/web.php` - Add dedicated routes for `/faq`, `/cgu`, `/privacy`
- `app/Http/Controllers/SeoController.php` - New methods: `faqPage()`, `cguPage()`, `privacyPage()` with unique meta
- `app/Application/Services/CharacterSeoService.php` - Add `getFaqMeta()`, `getCguMeta()`, `getPrivacyMeta()`

**Meta to define**:
- FAQ: "FAQ WowPlanet - Questions fréquentes | WowPlanet" + FAQPage JSON-LD schema
- CGU: "Conditions d'utilisation | WowPlanet"
- Privacy: "Politique de confidentialité | WowPlanet"

### 1.3 Optimize titles (< 60 chars)

**Problem**: Titles truncated in SERP (85+ chars). Keywords buried.

**Files to modify**:
- `app/Application/Services/DatabaseSeoService.php` - All title generation
- `app/Application/Services/CharacterSeoService.php` - Home title

**Title rewrites** (examples, to be refined after keyword research):

| Page | Current | New |
|------|---------|-----|
| Home | "WowPlanet - Suivi de progression World of Warcraft en français" (63 chars) | "WowPlanet - Suivi de progression WoW" (37 chars) |
| DB Index | "Base de données WoW en français - Montures, Hauts-faits, Quêtes, Mascottes \| WowPlanet" (85 chars) | "Base de données WoW complète \| WowPlanet" (41 chars) |
| Mounts | "Montures WoW - Liste complète des 1569 montures en français \| WowPlanet" (72 chars) | "Montures WoW - Liste des 1569 montures \| WowPlanet" (51 chars) |
| Quests zone | "Quêtes WoW {zone} ({exp}) - {n} quêtes en français \| WowPlanet" (~70 chars) | "Quêtes {zone} ({exp}) - {n} quêtes \| WowPlanet" (~55 chars) |

### 1.4 FAQPage schema

**Files to modify**:
- New FAQ meta method must include FAQPage JSON-LD with Q&A pairs extracted from `FaqPage.vue`

### 1.5 Remove unnecessary hreflang

**Files to modify**:
- `resources/views/welcome.blade.php` - Remove lines 14-15 (hreflang tags)

### 1.6 Fix hardcoded OG counts

**Files to modify**:
- `app/Application/Services/CharacterSeoService.php` - Lines 36-37: replace hardcoded "21 000 quêtes, 8 600 hauts-faits, 1 569 montures et 2 117 mascottes" with dynamic counts from DB

---

## Plan: Phase 2 - Content Enrichment (Priority: CRITICAL)

### 2.1 Programmatic descriptive content

**Problem**: Pages contain only raw name lists. Google considers this "thin content" — not worth indexing.

**Approach**: Generate descriptive paragraphs automatically from existing data.

**Data available per category**:
- Mount: category, source (obtention method), faction, expansion (from SA)
- Pet: category, source, creature_id, faction
- Achievement: category, expansion_id, points, faction
- Quest: expansion_id, zone_name, faction
- Decor: category, source
- Profession: name, recipe count per expansion

**Content template per category page** (mount example):

```
<h1>Montures WoW {category} - {count} montures</h1>
<p>Retrouvez les {count} montures {category} de World of Warcraft.
   {source_summary} {faction_summary}</p>
<h2>Statistiques</h2>
<p>{n_alliance} montures Alliance, {n_horde} montures Horde, {n_neutral} montures neutres.
   Extensions représentées : {expansion_list}.</p>
<h2>Toutes les montures {category}</h2>
<ul>...</ul>
```

**Content template per expansion page** (quest example):

```
<h1>Quêtes WoW {expansion} - {count} quêtes</h1>
<p>{expansion} est la {n}e extension de World of Warcraft.
   Elle contient {count} quêtes réparties dans {zone_count} zones.</p>
<h2>Zones de {expansion}</h2>
<nav><ul>{zone links with counts}</ul></nav>
```

**Files to modify**:
- `app/Application/Services/SeoContentRenderer.php` - Enrich all render methods with stats-based descriptive content
- No new database queries needed — use existing aggregate queries (groupBy category/faction/expansion)
- Expansion descriptions stored as constants in `ExpansionId` value object (name, number, theme)

### 2.2 Richer meta descriptions

**Problem**: Meta descriptions are generic and don't incite clicks.

Current: "Toutes les 1569 montures de World of Warcraft en français. Triées par catégorie avec source d'obtention, icône et lien Wowhead."

Better: "1569 montures WoW classées par source : donjons, raids, réputation, boutique. Trouvez comment obtenir chaque monture sur WowPlanet."

**Principle**: Descriptions should answer "why click?" not just "what's on the page".

**Files to modify**:
- `app/Application/Services/DatabaseSeoService.php` - All description generation

### 2.3 Low-hanging fruit pages (from GSC data)

These pages are already indexed at good positions but get 0 clicks:

| Page | Position | Action |
|------|----------|--------|
| `/professions/cuisine` | 1.2 | Enrich content + improve meta description to increase CTR |
| `/hauts-faits/midnight` | 1.45 | Enrich content + improve meta description |
| `/quetes/dragonflight/bastion-de-tyr` | 5.89 | Enrich content to push from pos 6 to top 3 |

---

## Plan: Phase 3 - Image & Performance (Priority: MEDIUM)

### 3.1 Alt text for images

**Files to modify**:
- `resources/js/components/CharacterCard.vue` - Add descriptive alt to avatar and class icon images
- `resources/js/pages/HomePage.vue` - Add alt to logo image

### 3.2 Preload hints

**Files to modify**:
- `resources/views/welcome.blade.php` - Add `<link rel="preload">` for Google Fonts CSS and critical above-the-fold font weight

### 3.3 Image alt in SSR content

The `#seo-content` item lists are text-only (no images). This is fine — Google indexes the text. No change needed.

---

## Plan: Phase 4 - Authority Building (Priority: LONG-TERM)

This phase cannot be implemented in code — it's a strategy.

### 4.1 Community presence

- Share WowPlanet on French WoW forums (JeuxOnline, forums Blizzard FR)
- Create a Discord server or join existing WoW FR communities
- Post on Reddit r/wow and r/wowfr

### 4.2 Content worth linking to

- Consider adding unique content that other sites would reference:
  - Expansion progression guides in French
  - Seasonal/patch content summaries
  - Statistics dashboards (most popular mounts, class distribution)
- This would naturally generate backlinks over time

### 4.3 Social signals

- Create Twitter/X account for WowPlanet
- Share updates, new features, WoW patch data refreshes

---

## Success Metrics (Measurable)

After implementing Phase 1 + 2, check GSC after 4-6 weeks:

| Metric | Current | Target (6 weeks) | Target (3 months) |
|--------|---------|-------------------|-------------------|
| Indexed pages | ~9 | 50+ | 100+ |
| Monthly impressions | ~70 | 500+ | 2000+ |
| Monthly organic clicks | ~26 (brand only) | 100+ | 500+ |
| Non-brand clicks | 0 | 20+ | 100+ |
| Position "monture wow" | 50 | <20 | <10 |
| Pages with 0 CTR at pos <5 | 2 | 0 | 0 |

---

## Implementation Order

1. **Phase 1** (1-2 days): Quick wins techniques
2. **Phase 2** (2-3 days): Content enrichment
3. **Phase 3** (half day): Images & performance
4. **Phase 4** (ongoing): Authority building

Total estimated code work: ~4-5 days for Phase 1-3.

---

## What This Plan Does NOT Cover

- Paid advertising (Google Ads, social ads)
- Link building outreach campaigns
- Multilingual SEO (English version)
- Mobile app SEO (App Store Optimization)
- Video content SEO (YouTube)

These could be future phases if organic traffic grows.
