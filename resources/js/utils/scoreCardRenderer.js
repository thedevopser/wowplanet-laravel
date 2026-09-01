import { dimensionColor, getScoreColor, getRankColorHex } from './scoreDisplay';
import { classColors } from './classColors';

const WIDTH = 700;
const ROW_H = 30;
// Hauteur de la carte hors lignes de dimensions.
const CHROME_H = 220;
const GOLD = '#d4a844';
const BG = '#0f172a';
const BG_LIGHTER = '#1e293b';
const FONT = 'system-ui, -apple-system, sans-serif';

function roundRect(ctx, x, y, w, h, r) {
    if (ctx.roundRect) {
        ctx.beginPath();
        ctx.roundRect(x, y, w, h, r);
    } else {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }
}

export function renderScoreCard({ variant, characterName, characterRealm, characterClass, characterRace, characterLevel, classId, characterCount, globalScore, rank, dimensions }) {
    const rows = Array.isArray(dimensions) ? dimensions.filter(d => d.applicable) : [];
    const HEIGHT = CHROME_H + rows.length * ROW_H;

    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = HEIGHT;
    const ctx = canvas.getContext('2d');

    // Background gradient
    const bgGrad = ctx.createLinearGradient(0, 0, WIDTH, HEIGHT);
    bgGrad.addColorStop(0, BG_LIGHTER);
    bgGrad.addColorStop(1, BG);
    ctx.fillStyle = bgGrad;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    // Gold accent line top
    ctx.fillStyle = GOLD;
    ctx.fillRect(0, 0, WIDTH, 3);

    // Header
    ctx.font = `bold 13px ${FONT}`;
    ctx.fillStyle = GOLD;
    ctx.textBaseline = 'top';
    ctx.fillText('WOWPLANET', 24, 16);

    ctx.font = `500 12px ${FONT}`;
    ctx.fillStyle = '#94a3b8';
    ctx.textAlign = 'right';
    ctx.fillText('Score de Completion', WIDTH - 24, 17);
    ctx.textAlign = 'left';

    // Character / Account info
    let infoY = 44;
    if (variant === 'personal') {
        ctx.font = `bold 18px ${FONT}`;
        ctx.fillStyle = classColors[classId] || '#FFFFFF';
        ctx.fillText(characterName || '', 24, infoY);

        const nameWidth = ctx.measureText(characterName || '').width;
        ctx.font = `400 13px ${FONT}`;
        ctx.fillStyle = '#94a3b8';
        ctx.fillText(` — ${characterRace || ''} ${characterClass || ''} ${characterLevel || ''} | ${characterRealm || ''}`, 24 + nameWidth, infoY + 3);
    } else {
        ctx.font = `bold 18px ${FONT}`;
        ctx.fillStyle = '#FFFFFF';
        ctx.fillText('Score Compte', 24, infoY);

        ctx.font = `400 13px ${FONT}`;
        ctx.fillStyle = '#64748b';
        ctx.fillText(`${characterCount || 0} personnage${(characterCount || 0) > 1 ? 's' : ''} analyse${(characterCount || 0) > 1 ? 's' : ''}`, 24, infoY + 24);
    }

    // Global score
    const scoreY = variant === 'personal' ? 85 : 95;
    const scoreColor = getScoreColor(globalScore || 0);
    const rankColor = getRankColorHex(globalScore || 0);

    // Score number
    ctx.font = `900 52px ${FONT}`;
    ctx.fillStyle = scoreColor;
    ctx.textAlign = 'center';
    ctx.fillText(String(globalScore ?? 0), WIDTH / 2 - 30, scoreY);

    // "/ 100"
    const scoreTextWidth = ctx.measureText(String(globalScore ?? 0)).width;
    ctx.font = `bold 20px ${FONT}`;
    ctx.fillStyle = '#64748b';
    ctx.fillText('/ 100', WIDTH / 2 - 30 + scoreTextWidth / 2 + 12, scoreY + 24);

    // Rank badge
    const badgeY = scoreY + 62;
    const rankText = (rank || '').toUpperCase();
    ctx.font = `800 11px ${FONT}`;
    const badgeW = ctx.measureText(rankText).width + 24;
    const badgeX = WIDTH / 2 - badgeW / 2;

    roundRect(ctx, badgeX, badgeY, badgeW, 24, 12);
    ctx.fillStyle = rankColor + '25';
    ctx.fill();
    ctx.strokeStyle = rankColor + '60';
    ctx.lineWidth = 1;
    ctx.stroke();

    ctx.fillStyle = rankColor;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(rankText, WIDTH / 2, badgeY + 12);

    // Dimension rows
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';

    const startY = badgeY + 42;
    const labelX = 24;
    const barX = 160;
    const barW = 320;
    const barH = 12;
    const pctX = barX + barW + 14;
    const detailX = pctX + 50;

    for (let i = 0; i < rows.length; i++) {
        const dim = rows[i];
        const y = startY + i * ROW_H;
        const pct = Math.round(dim.score || 0);
        const color = dimensionColor(dim.key);

        // Label
        ctx.font = `600 13px ${FONT}`;
        ctx.fillStyle = '#cbd5e1';
        ctx.fillText(dim.label, labelX, y);

        // Bar background
        roundRect(ctx, barX, y + 1, barW, barH, 6);
        ctx.fillStyle = '#1e293b';
        ctx.fill();

        // Bar filled
        if (pct > 0) {
            const fillW = Math.max(12, (pct / 100) * barW);
            roundRect(ctx, barX, y + 1, fillW, barH, 6);
            ctx.fillStyle = color;
            ctx.fill();
        }

        // Percentage
        ctx.font = `bold 13px ${FONT}`;
        ctx.fillStyle = color;
        ctx.fillText(`${pct}%`, pctX, y);

        // Completed / total
        ctx.font = `400 10px ${FONT}`;
        ctx.fillStyle = '#64748b';
        const completed = dim.completed?.toLocaleString('fr-FR') ?? '0';
        const total = dim.total?.toLocaleString('fr-FR') ?? '0';
        ctx.fillText(`${completed}/${total}`, detailX, y + 2);
    }

    // Footer
    ctx.font = `500 11px ${FONT}`;
    ctx.fillStyle = GOLD + 'AA';
    ctx.textAlign = 'center';
    ctx.fillText('wowplanet.fr', WIDTH / 2, HEIGHT - 18);

    // Bottom gold line
    ctx.fillStyle = GOLD;
    ctx.fillRect(0, HEIGHT - 3, WIDTH, 3);

    return canvas;
}
