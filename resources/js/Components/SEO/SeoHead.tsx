import { brandName } from '../../lib/brand';
import { Head } from '@inertiajs/react';

/* Inertia's Head component uses the non-standard head-key attribute to deduplicate tags. */
/* eslint-disable react/no-unknown-property */

type SeoPayload = {
  title?: string;
  description?: string;
  keywords?: string;
  canonical?: string;
  url?: string;
  image?: string;
  site_name?: string;
  locale?: string;
  type?: string;
  robots?: string;
  twitter_card?: string;
  schemas?: Array<Record<string, any>>;
};

export default function SeoHead({ seo }: { seo?: SeoPayload }) {
  if (!seo) {
    return null;
  }

  const title = seo.title || seo.site_name || `${brandName()}`;
  const description = seo.description || '';
  const canonical = seo.canonical || seo.url;
  const image = seo.image;

  return (
    <Head title={title}>
      {description && <meta head-key="description" name="description" content={description} />}
      {seo.keywords && <meta head-key="keywords" name="keywords" content={seo.keywords} />}
      {seo.robots && <meta head-key="robots" name="robots" content={seo.robots} />}
      {canonical && <link head-key="canonical" rel="canonical" href={canonical} />}

      <meta head-key="og:type" property="og:type" content={seo.type || 'website'} />
      <meta head-key="og:title" property="og:title" content={title} />
      {description && (
        <meta head-key="og:description" property="og:description" content={description} />
      )}
      {seo.url && <meta head-key="og:url" property="og:url" content={seo.url} />}
      {seo.site_name && (
        <meta head-key="og:site_name" property="og:site_name" content={seo.site_name} />
      )}
      {seo.locale && <meta head-key="og:locale" property="og:locale" content={seo.locale} />}
      {image && <meta head-key="og:image" property="og:image" content={image} />}
      {image && (
        <meta
          head-key="og:image:alt"
          property="og:image:alt"
          content={`${seo.site_name || `${brandName()}`} product preview`}
        />
      )}

      <meta
        head-key="twitter:card"
        name="twitter:card"
        content={seo.twitter_card || 'summary_large_image'}
      />
      <meta head-key="twitter:title" name="twitter:title" content={title} />
      {description && (
        <meta head-key="twitter:description" name="twitter:description" content={description} />
      )}
      {image && <meta head-key="twitter:image" name="twitter:image" content={image} />}

      {(seo.schemas || []).map((schema, index) => (
        <script
          key={`schema-${index}`}
          head-key={`schema-${index}`}
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
        />
      ))}
    </Head>
  );
}
