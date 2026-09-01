// Présentation du score. Le calcul est côté serveur (App\Domain\Services\ScoreCalculator).

const DIMENSION_COLORS = {
    quests: '#3b82f6',
    achievements: '#f59e0b',
    reputations: '#a855f7',
    raids: '#f43f5e',
    mounts: '#f59e0b',
    transmog: '#a78bfa',
    pets: '#3b82f6',
    decor: '#8b5cf6',
    professions: '#10b981',
};

const RANK_CLASSES = {
    'Légendaire': 'bg-orange-500/10 text-orange-400 border-orange-500/30',
    'Épique': 'bg-purple-500/10 text-purple-400 border-purple-500/30',
    'Rare': 'bg-blue-500/10 text-blue-400 border-blue-500/30',
    'Commun': 'bg-green-500/10 text-green-400 border-green-500/30',
};

const NEUTRAL_RANK_CLASS = 'bg-slate-500/10 text-slate-400 border-slate-500/30';

export function dimensionColor(key) {
    return DIMENSION_COLORS[key] || '#64748b';
}

export function rankClass(rank) {
    return RANK_CLASSES[rank] || NEUTRAL_RANK_CLASS;
}

/** Les dimensions sans données sortent du radar : un axe à 0 déformerait le polygone. */
export function applicableDimensions(score) {
    return (score?.dimensions || []).filter(d => d.applicable);
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

export { DIMENSION_COLORS };
