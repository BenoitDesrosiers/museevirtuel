/**
 * Formate une durée en secondes au format mm:ss.
 *
 * Arrondit à la seconde entière pour éviter les décimales parasites
 * (ex : 125.5 → "2:06" et non "2:5.5").
 */
export function formatDuree(sec: number): string {
    const total = Math.round(sec);
    const m = Math.floor(total / 60);
    const s = total % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}
