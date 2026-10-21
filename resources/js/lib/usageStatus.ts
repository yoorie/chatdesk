/**
 * Plain-language status for a plan usage meter.
 *
 * - "count" limits (WhatsApp connections, agents) are allowances: using all of them is
 *   normal, so we only warn when usage is actually over the limit.
 * - "consumable" limits (messages, template sends, AI credits) run out, so we also warn
 *   when they are nearly used up.
 */
export type UsageKind = 'count' | 'consumable';
export type UsageLevel = 'ok' | 'near' | 'over';

export interface UsageStatus {
  unlimited: boolean;
  percent: number;
  level: UsageLevel;
  summary: string;
  note: string | null;
}

export const NEAR_LIMIT_PERCENT = 85;

const UNLIMITED_VALUES = new Set([-1, 9999, 9999999]);

const fmt = (value: number) => Math.max(0, Math.round(value)).toLocaleString('en-IN');

export function isUnlimitedLimit(limit: number | null | undefined): boolean {
  return limit === null || limit === undefined || UNLIMITED_VALUES.has(Number(limit));
}

export function usageStatus(
  used: number | null | undefined,
  limit: number | null | undefined,
  kind: UsageKind
): UsageStatus {
  const usedValue = Number(used ?? 0) || 0;

  if (isUnlimitedLimit(limit)) {
    return {
      unlimited: true,
      percent: 0,
      level: 'ok',
      summary: `${fmt(usedValue)} used (unlimited)`,
      note: null,
    };
  }

  const limitValue = Number(limit);
  const summary = `${fmt(usedValue)} of ${fmt(limitValue)} used`;

  if (limitValue <= 0) {
    return {
      unlimited: false,
      percent: usedValue > 0 ? 100 : 0,
      level: usedValue > 0 ? 'over' : 'ok',
      summary,
      note: usedValue > 0 ? 'Your plan does not include this. Upgrade to use it.' : null,
    };
  }

  const percent = Math.min((usedValue / limitValue) * 100, 100);

  if (usedValue > limitValue) {
    return {
      unlimited: false,
      percent,
      level: 'over',
      summary,
      note:
        kind === 'count'
          ? 'Over your plan limit. Remove some or upgrade your plan.'
          : 'Over your plan limit. Upgrade your plan or buy credits.',
    };
  }

  if (kind === 'consumable') {
    if (usedValue >= limitValue) {
      return {
        unlimited: false,
        percent,
        level: 'over',
        summary,
        note: 'All used. Upgrade your plan or buy credits to keep going.',
      };
    }
    if (percent >= NEAR_LIMIT_PERCENT) {
      return {
        unlimited: false,
        percent,
        level: 'near',
        summary,
        note: 'Almost used up. Upgrade your plan or buy credits.',
      };
    }
  }

  return { unlimited: false, percent, level: 'ok', summary, note: null };
}
