import { computeScore, DIMENSION_LABELS, DIMENSION_COLORS, WEIGHTS } from './scoreCalculator';

/**
 * Incrementally aggregate multiple character profiles into one virtual "account" profile.
 * - Mounts/Pets/Decor: account-wide, taken from first character.
 * - Quests/Achievements: union of completed item IDs across all characters.
 * - Reputations: best completed count per expansion.
 * - Professions: union across characters (different chars have different profs).
 */
export function createAccountAggregator() {
    let collectionsTotals = null;    // { expId: { quests: { total, zones }, achievements: { total, categories } } }
    let completedQuestIds = new Set();
    let completedAchievementIds = new Set();
    let bestReputations = {};        // { expId: { completed, total } }
    let accountMounts = null;
    let accountPets = null;
    let accountDecor = null;
    let professionMap = {};          // { profId: professionData }
    let loadedCount = 0;

    function mergeCharacter(character) {
        if (!character) return;
        loadedCount++;

        // Mounts/Pets/Decor are account-wide — take from first character
        if (!accountMounts) accountMounts = character.mounts || [];
        if (!accountPets) accountPets = character.pets || [];
        if (!accountDecor) accountDecor = character.decor || [];

        // Quests & Achievements: build union of completed IDs
        const collections = character.collections || {};
        for (const expId in collections) {
            const exp = collections[expId];

            // Initialize totals from first character (totals are same for all)
            if (!collectionsTotals) collectionsTotals = {};
            if (!collectionsTotals[expId]) {
                collectionsTotals[expId] = {
                    quests: { total: exp.quests?.total || 0, zones: exp.quests?.zones || [] },
                    achievements: { total: exp.achievements?.total || 0, categories: exp.achievements?.categories || [] },
                    reputations: { total: exp.reputations?.total || 0 },
                };
            }

            // Union quest IDs
            for (const zone of (exp.quests?.zones || [])) {
                for (const item of (zone.items || [])) {
                    if (item.is_completed) completedQuestIds.add(item.id);
                }
            }

            // Union achievement IDs
            for (const cat of (exp.achievements?.categories || [])) {
                for (const item of (cat.items || [])) {
                    if (item.is_completed) completedAchievementIds.add(item.id);
                }
            }

            // Reputations: keep best completed count per expansion
            const repCompleted = exp.reputations?.completed || 0;
            const repTotal = exp.reputations?.total || 0;
            if (!bestReputations[expId] || repCompleted > bestReputations[expId].completed) {
                bestReputations[expId] = { completed: repCompleted, total: repTotal };
            }
        }

        // Professions: union across characters
        for (const prof of (character.professions || [])) {
            const pid = prof.profession_id;
            if (!professionMap[pid]) {
                professionMap[pid] = JSON.parse(JSON.stringify(prof));
            } else {
                // Merge expansions: keep best per expansion
                for (const expId in prof.expansions) {
                    const existing = professionMap[pid].expansions[expId];
                    const incoming = prof.expansions[expId];
                    if (!existing) {
                        professionMap[pid].expansions[expId] = { ...incoming };
                    } else {
                        if ((incoming.completed || 0) > (existing.completed || 0)) {
                            existing.completed = incoming.completed;
                        }
                        if ((incoming.skill_points || 0) > (existing.skill_points || 0)) {
                            existing.skill_points = incoming.skill_points;
                        }
                        // Keep totals/max from whichever is higher
                        existing.total = Math.max(existing.total || 0, incoming.total || 0);
                        existing.max_skill_points = Math.max(existing.max_skill_points || 0, incoming.max_skill_points || 0);
                    }
                }
            }
        }
    }

    function buildVirtualProfile() {
        if (!collectionsTotals) return null;

        // Rebuild collections with account-wide completion
        const collections = {};
        for (const expId in collectionsTotals) {
            const totals = collectionsTotals[expId];

            // Recompute quest completed count from union
            let questCompleted = 0;
            const zones = totals.quests.zones.map(zone => {
                const items = (zone.items || []).map(item => {
                    const completed = completedQuestIds.has(item.id);
                    if (completed) questCompleted++;
                    return { ...item, is_completed: completed };
                });
                return { ...zone, items, completed: items.filter(i => i.is_completed).length };
            });

            // Recompute achievement completed count from union
            let achCompleted = 0;
            const categories = totals.achievements.categories.map(cat => {
                const items = (cat.items || []).map(item => {
                    const completed = completedAchievementIds.has(item.id);
                    if (completed) achCompleted++;
                    return { ...item, is_completed: completed };
                });
                return { ...cat, items, completed: items.filter(i => i.is_completed).length };
            });

            const rep = bestReputations[expId] || { completed: 0, total: totals.reputations.total };

            collections[expId] = {
                quests: { total: totals.quests.total, completed: questCompleted, zones },
                achievements: { total: totals.achievements.total, completed: achCompleted, categories },
                reputations: { completed: rep.completed, total: rep.total },
            };
        }

        return {
            collections,
            mounts: accountMounts || [],
            pets: accountPets || [],
            decor: accountDecor || [],
            professions: Object.values(professionMap),
            mountsCount: (accountMounts || []).filter(m => m.is_completed).length,
            petsCount: (accountPets || []).filter(p => p.is_completed).length,
            decorCount: (accountDecor || []).filter(d => d.is_completed).length,
        };
    }

    function getScore() {
        const profile = buildVirtualProfile();
        return profile ? computeScore(profile) : null;
    }

    function getLoadedCount() {
        return loadedCount;
    }

    function getVirtualProfile() {
        return buildVirtualProfile();
    }

    return { mergeCharacter, getScore, getLoadedCount, getVirtualProfile };
}

export { DIMENSION_LABELS, DIMENSION_COLORS, WEIGHTS };
