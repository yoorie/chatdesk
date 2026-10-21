/** Savings compare the billed yearly total with twelve monthly payments, in minor units. */
export function annualSavings(
  monthly: number,
  yearly?: number | null
): { amount: number; percent: number } | null {
  if (
    !Number.isFinite(monthly) ||
    !Number.isFinite(yearly) ||
    monthly <= 0 ||
    yearly == null ||
    yearly <= 0
  )
    return null;
  const baseline = monthly * 12;
  const amount = baseline - yearly;
  if (amount <= 0) return null;
  return { amount, percent: Math.round((amount / baseline) * 1000) / 10 };
}
