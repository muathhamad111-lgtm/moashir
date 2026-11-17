import LandingHero from '@/components/landing/LandingHero.vue'
import LandingStats from '@/components/landing/LandingStats.vue'
import LandingAbout from '@/components/landing/LandingAbout.vue'
import LandingModel from '@/components/landing/LandingModel.vue'
import LandingPaths from '@/components/landing/LandingPaths.vue'
import LandingJourney from '@/components/landing/LandingJourney.vue'
import LandingBand from '@/components/landing/LandingBand.vue'
import LandingLevels from '@/components/landing/LandingLevels.vue'
import LandingKit from '@/components/landing/LandingKit.vue'
import LandingLenses from '@/components/landing/LandingLenses.vue'
import LandingLeaders from '@/components/landing/LandingLeaders.vue'
import LandingPartners from '@/components/landing/LandingPartners.vue'
import LandingFinal from '@/components/landing/LandingFinal.vue'
import LandingContact from '@/components/landing/LandingContact.vue'

export const LANDING_SECTION_VIEWS = Object.freeze({
  hero: { component: LandingHero, anchor: 'hero' },
  kpi_promo: { component: LandingStats, anchor: 'stats' },
  about: { component: LandingAbout, anchor: 'about' },
  excellence_model: { component: LandingModel, anchor: 'model' },
  paths: { component: LandingPaths, anchor: 'paths' },
  'how_it_works:journey': { component: LandingJourney, anchor: 'journey' },
  'rich_text:band': { component: LandingBand, anchor: 'begin' },
  levels: { component: LandingLevels, anchor: 'levels' },
  bag_promo: { component: LandingKit, anchor: 'kit' },
  lenses_brief: { component: LandingLenses, anchor: 'lenses' },
  leadership_promo: { component: LandingLeaders, anchor: 'leaders' },
  partners: { component: LandingPartners, anchor: 'partners' },
  'rich_text:cta': { component: LandingFinal, anchor: 'start' },
  contact: { component: LandingContact, anchor: 'contact' },
})

export function landingSectionView(section) {
  const type = String(section?.type ?? '').replaceAll('-', '_')
  const variant = section?.meta?.variant
  return (
    (variant && LANDING_SECTION_VIEWS[`${type}:${variant}`]) || LANDING_SECTION_VIEWS[type] || null
  )
}

export function landingEntries(sections) {
  const used = new Set()
  return (sections ?? [])
    .map((section) => ({ key: section.id, section, view: landingSectionView(section) }))
    .filter((entry) => entry.view)
    .map((entry) => {
      const anchor = used.has(entry.view.anchor) ? null : entry.view.anchor
      used.add(entry.view.anchor)
      return { ...entry, anchor }
    })
}
