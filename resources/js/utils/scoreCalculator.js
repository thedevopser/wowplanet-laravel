const WEIGHTS = {
    quests: 0.15,
    achievements: 0.25,
    reputations: 0.15,
    mounts: 0.15,
    pets: 0.10,
    decor: 0.10,
    professions: 0.10,
};

const DIMENSION_LABELS = {
    quests: 'Quêtes',
    achievements: 'Hauts-faits',
    reputations: 'Réputations',
    mounts: 'Montures',
    pets: 'Mascottes',
    decor: 'Décorations',
    professions: 'Métiers',
};

const DIMENSION_COLORS = {
    quests: '#3b82f6',
    achievements: '#f59e0b',
    reputations: '#a855f7',
    mounts: '#f59e0b',
    pets: '#3b82f6',
    decor: '#8b5cf6',
    professions: '#10b981',
};

function sumCollections(collections, type) {
    let completed = 0;
    let total = 0;
    for (const expId in collections) {
        const data = collections[expId]?.[type];
        if (data) {
            completed += data.completed || 0;
            total += data.total || 0;
        }
    }
    return { completed, total };
}

function countCollectionItems(items) {
    const total = items.length;
    const completed = items.filter(i => i.is_completed).length;
    return { completed, total };
}

function sumProfessionProgress(professions) {
    let recipeCompleted = 0;
    let recipeTotal = 0;
    let skillPoints = 0;
    let skillMax = 0;

    for (const prof of professions) {
        for (const expId in prof.expansions) {
            const data = prof.expansions[expId];
            recipeCompleted += data.completed || 0;
            recipeTotal += data.total || 0;
            skillPoints += data.skill_points || 0;
            skillMax += data.max_skill_points || 0;
        }
    }

    // Use recipes if available, otherwise fall back to skill points
    if (recipeTotal > 0) {
        return { completed: recipeCompleted, total: recipeTotal };
    }
    return { completed: skillPoints, total: skillMax };
}

function dimensionScore(completed, total) {
    if (total === 0) return 0;
    return (completed / total) * 100;
}

export function computeScore(character) {
    if (!character) return null;

    const questStats = sumCollections(character.collections || {}, 'quests');
    const achievementStats = sumCollections(character.collections || {}, 'achievements');
    const reputationStats = sumCollections(character.collections || {}, 'reputations');
    const mountStats = countCollectionItems(character.mounts || []);
    const petStats = countCollectionItems(character.pets || []);
    const decorStats = countCollectionItems(character.decor || []);
    const professionStats = sumProfessionProgress(character.professions || []);

    const dimensions = {
        quests: { ...questStats, score: dimensionScore(questStats.completed, questStats.total) },
        achievements: { ...achievementStats, score: dimensionScore(achievementStats.completed, achievementStats.total) },
        reputations: { ...reputationStats, score: dimensionScore(reputationStats.completed, reputationStats.total) },
        mounts: { ...mountStats, score: dimensionScore(mountStats.completed, mountStats.total) },
        pets: { ...petStats, score: dimensionScore(petStats.completed, petStats.total) },
        decor: { ...decorStats, score: dimensionScore(decorStats.completed, decorStats.total) },
        professions: { ...professionStats, score: dimensionScore(professionStats.completed, professionStats.total) },
    };

    let global = 0;
    for (const [key, weight] of Object.entries(WEIGHTS)) {
        global += dimensions[key].score * weight;
    }

    return { global: Math.round(global * 10) / 10, dimensions };
}

export function getScoreColor(score) {
    if (score >= 75) return '#22c55e';
    if (score >= 50) return '#eab308';
    if (score >= 25) return '#f97316';
    return '#ef4444';
}

export function getRankColorHex(score) {
    if (score >= 90) return '#f97316';
    if (score >= 75) return '#a855f7';
    if (score >= 50) return '#3b82f6';
    if (score >= 25) return '#22c55e';
    return '#94a3b8';
}

export function getScoreTailwindColor(score) {
    if (score >= 75) return 'text-green-400';
    if (score >= 50) return 'text-yellow-400';
    if (score >= 25) return 'text-orange-400';
    return 'text-red-400';
}

export { WEIGHTS, DIMENSION_LABELS, DIMENSION_COLORS };
