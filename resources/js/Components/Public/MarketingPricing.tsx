import { annualSavings } from '@/lib/pricing';
import { Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { useState } from 'react';

export interface MarketingPlan {
  id: number;
  name: string;
  key: string;
  description?: string | null;
  price_monthly: number;
  price_yearly?: number | null;
  currency?: string;
  trial_days?: number;
  features: string[];
}

export function publicPlans(plans: MarketingPlan[]) {
  return plans.filter((plan) => !['enterprise', 'free'].includes(plan.key)).slice(0, 3);
}

/** The same pricing presentation and billing behavior on the homepage and pricing page. */
export default function MarketingPricing({
  plans,
  canRegister = true,
  headingLevel = 2,
}: {
  plans: MarketingPlan[];
  canRegister?: boolean;
  headingLevel?: 1 | 2;
}) {
  const [annual, setAnnual] = useState(false);
  const Heading = headingLevel === 1 ? 'h1' : 'h2';
  const CardHeading = headingLevel === 1 ? 'h2' : 'h3';
  const visiblePlans = publicPlans(plans);
  const maxSavings = Math.max(
    0,
    ...visiblePlans.map(
      (plan) => annualSavings(plan.price_monthly, plan.price_yearly)?.percent || 0
    )
  );
  const formatPrice = (amount: number, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency,
      maximumFractionDigits: 0,
    }).format(amount / 100);

  return (
    <div data-marketing-pricing>
      <div className="flex flex-wrap items-end justify-between gap-6">
        <div>
          <p className="mkt-eyebrow">Simple & transparent pricing</p>
          <Heading className="mkt-heading mt-3">Plans for every stage.</Heading>
          <p className="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">
            One workspace. One WhatsApp number. Your whole team.
          </p>
        </div>
        <div className="mkt-billing-toggle" role="group" aria-label="Billing cycle">
          {[false, true].map((yearly) => (
            <button
              key={String(yearly)}
              type="button"
              aria-pressed={annual === yearly}
              onClick={() => setAnnual(yearly)}
              className={annual === yearly ? 'is-active' : ''}
            >
              {yearly ? 'Yearly' : 'Monthly'}{' '}
              {yearly && maxSavings > 0 && (
                <span className="ml-2 rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                  Save up to {maxSavings}%
                </span>
              )}
            </button>
          ))}
        </div>
      </div>
      <div className="mt-8 grid gap-5 md:grid-cols-3">
        {visiblePlans.map((plan, index) => {
          const highlighted = index === 1;
          const yearly = annual && Boolean(plan.price_yearly);
          const savings = yearly ? annualSavings(plan.price_monthly, plan.price_yearly) : null;
          const amount = yearly ? Math.round(plan.price_yearly! / 12) : plan.price_monthly;
          return (
            <article key={plan.id} className={`mkt-price-card ${highlighted ? 'is-featured' : ''}`}>
              <div className="flex min-h-6 items-center justify-between gap-2">
                <CardHeading className="font-semibold">{plan.name}</CardHeading>
                <div className="flex flex-wrap justify-end gap-1.5">
                  {savings && savings.percent > 0 && (
                    <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                      Save {savings.percent}%
                    </span>
                  )}
                  {highlighted && (
                    <span className="rounded-full bg-ui-green px-3 py-1 text-[10px] font-semibold text-white">
                      Most popular
                    </span>
                  )}
                </div>
              </div>
              <p className="mt-4 text-4xl font-semibold tracking-tight tabular-nums">
                {formatPrice(amount, plan.currency)}
                <span className="ml-1 text-sm font-normal tracking-normal text-slate-500 dark:text-slate-400">
                  /month
                </span>
              </p>
              <p className="mt-2 min-h-5 text-xs text-slate-500 dark:text-slate-400">
                {savings && (
                  <>
                    <s>{formatPrice(plan.price_monthly, plan.currency)}/month</s>
                    <span className="ml-2 font-medium text-ui-green">
                      Save {formatPrice(savings.amount, plan.currency)}/year
                    </span>
                  </>
                )}
              </p>
              <p className="mt-2 min-h-6 text-sm leading-5 text-slate-500 dark:text-slate-400">
                {yearly
                  ? `Billed ${formatPrice(plan.price_yearly!, plan.currency)} yearly`
                  : plan.description || 'A workspace for your team.'}
              </p>
              <ul className="my-6 space-y-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                {plan.features
                  .filter((feature) => !/free trial/i.test(feature))
                  .slice(0, 4)
                  .map((feature) => (
                    <li
                      key={feature}
                      className="flex items-start gap-2 text-sm leading-5 text-slate-600 dark:text-slate-300"
                    >
                      <Check size={15} className="mt-0.5 shrink-0 text-ui-green" />
                      {feature}
                    </li>
                  ))}
              </ul>
              <Link
                href={
                  canRegister
                    ? `${route('register')}?plan=${plan.key}&cycle=${yearly ? 'yearly' : 'monthly'}`
                    : route('contact')
                }
                className={`${highlighted ? 'mkt-button-primary' : 'mkt-button-secondary'} mt-auto w-full`}
              >
                {canRegister ? 'Get started' : 'Contact sales'}
                <ArrowRight size={14} />
              </Link>
              <p className="mt-3 min-h-4 text-center text-[11px] text-slate-500 dark:text-slate-400">
                {plan.trial_days ? (
                  <span className="inline-flex rounded-md bg-slate-50 px-2.5 py-1 dark:bg-slate-800">
                    {plan.trial_days}-day free trial
                  </span>
                ) : (
                  'Choose the plan that fits your team'
                )}
              </p>
            </article>
          );
        })}
      </div>
      <div className="mt-5 flex flex-col justify-between gap-3 rounded-xl border border-slate-200/80 px-5 py-4 text-sm dark:border-slate-800 sm:flex-row sm:items-center">
        <p className="text-slate-500 dark:text-slate-400">
          <strong className="font-semibold text-slate-800 dark:text-slate-200">
            Need something bigger?
          </strong>{' '}
          Let's talk about Enterprise.
        </p>
        <Link
          href={route('contact')}
          className="inline-flex shrink-0 items-center gap-2 font-semibold text-ui-green"
        >
          Talk to sales
          <ArrowRight size={14} />
        </Link>
      </div>
      <p className="mt-4 text-center text-[11px] leading-6 text-slate-500 dark:text-slate-400">
        Plans are billed per workspace. Meta messaging charges apply separately. Enterprise
        activation requires review.
      </p>
    </div>
  );
}
