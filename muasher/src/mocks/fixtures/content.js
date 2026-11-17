import { fixtureImage } from './cmsImages'
import {
  landingHeaderLinks,
  landingHomeSeo,
  landingHomeTitle,
  landingQuickLinks,
  landingSectionDefinitions,
} from './landingHome'

function localizedOrNull(value) {
  return value && (value.ar || value.en) ? value : null
}

function contentFields(overrides) {
  return {
    title: localizedOrNull(overrides.title),
    subtitle: localizedOrNull(overrides.subtitle),
    description: localizedOrNull(overrides.description),
    ctaLabel: localizedOrNull(overrides.ctaLabel),
    ctaUrl: overrides.ctaUrl || null,
    icon: overrides.icon || null,
    image: overrides.image ? fixtureImage(overrides.image) : null,
    meta: overrides.meta && Object.keys(overrides.meta).length ? overrides.meta : null,
  }
}

export function section(overrides) {
  return {
    id: overrides.id,
    key: overrides.key,
    type: overrides.type,
    enabled: overrides.enabled ?? true,
    order: overrides.order,
    ...contentFields(overrides),
    background: overrides.background || null,
    items: overrides.items ?? [],
  }
}

export function item(overrides) {
  return {
    id: overrides.id,
    enabled: overrides.enabled ?? true,
    order: overrides.order,
    ...contentFields(overrides),
  }
}

export const homePageFixture = {
  key: 'home',
  title: landingHomeTitle,
  seo: landingHomeSeo,
  published: true,
  sections: landingSectionDefinitions.map((definition) =>
    section({ ...definition, items: definition.items.map(item) }),
  ),
}

export const siteSettingsFixture = {
  platformName: { ar: 'مؤشر', en: 'Moashir' },
  platformDescription: {
    ar: 'منصة رقمية لقياس النضج المؤسسي للجمعيات والمؤسسات الأهلية والأوقاف وفق نموذج التميّز في العمل الخيري.',
    en: 'A digital platform that measures the institutional maturity of civil associations, civil foundations and endowments against the Charitable Work Excellence Model.',
  },
  headerLinks: landingHeaderLinks,
  social: [
    { platform: 'x', url: 'https://x.com/moashir.example' },
    { platform: 'linkedin', url: 'https://www.linkedin.com/company/moashir-example' },
    { platform: 'youtube', url: 'https://www.youtube.com/@moashir.example' },
  ],
  contact: {
    email: 'info@moashir.example.sa',
    phone: '+966 11 000 0000',
    address: {
      ar: 'الرياض، المملكة العربية السعودية',
      en: 'Riyadh, Kingdom of Saudi Arabia',
    },
  },
  footerNote: {
    ar: 'جميع الحقوق محفوظة لمنصة مؤشر.',
    en: 'All rights reserved to the Moashir platform.',
  },
  quickLinks: landingQuickLinks,
  seo: {
    title: { ar: 'منصة مؤشر', en: 'Moashir' },
    description: {
      ar: 'بوابة التقييم المؤسسي وفق نموذج التميز في العمل الخيري.',
      en: 'The institutional assessment portal of the Charitable Work Excellence Model.',
    },
  },
  featuredKpiLimit: 6,
  showFeaturedKpis: true,
}
