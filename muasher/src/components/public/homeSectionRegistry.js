import { HOME_ANCHORS } from '@/constants/homeSections'
import HomeHero from '@/components/public/HomeHero.vue'
import HomeStatsStrip from '@/components/public/HomeStatsStrip.vue'
import HomeTour from '@/components/public/HomeTour.vue'
import HomeJourney from '@/components/public/HomeJourney.vue'
import HomeEvaluationPaths from '@/components/public/HomeEvaluationPaths.vue'
import HomeExcellenceModel from '@/components/public/HomeExcellenceModel.vue'
import HomeLevels from '@/components/public/HomeLevels.vue'
import HomeFastKit from '@/components/public/HomeFastKit.vue'
import HomeCtaBanner from '@/components/public/HomeCtaBanner.vue'
import HomeLeadership from '@/components/public/HomeLeadership.vue'
import HomeContact from '@/components/public/HomeContact.vue'

const SECTION_VIEWS = {
  hero: { component: HomeHero, anchor: HOME_ANCHORS.hero },
  how_it_works: { component: HomeTour, anchor: HOME_ANCHORS.tour },
  'how_it_works:journey': { component: HomeJourney, anchor: HOME_ANCHORS.journey },
  paths: { component: HomeEvaluationPaths, anchor: HOME_ANCHORS.paths },
  excellence_model: { component: HomeExcellenceModel, anchor: HOME_ANCHORS.model },
  bag_promo: { component: HomeFastKit, anchor: HOME_ANCHORS.kit },
  'rich_text:cta': { component: HomeCtaBanner, anchor: HOME_ANCHORS.start },
  leadership_promo: { component: HomeLeadership, anchor: HOME_ANCHORS.leaders },
  contact: { component: HomeContact, anchor: HOME_ANCHORS.contact, needsSiteSettings: true },
}

const STATS_ENTRY = { key: 'stats', view: { component: HomeStatsStrip, anchor: null } }
const LEVELS_ENTRY = { key: 'levels', view: { component: HomeLevels, anchor: HOME_ANCHORS.levels } }

export function homeSectionView(section) {
  const type = String(section?.type ?? '').replaceAll('-', '_')
  const variant = section?.meta?.variant
  return (variant && SECTION_VIEWS[`${type}:${variant}`]) || SECTION_VIEWS[type] || null
}

function insertAfter(entries, anchor, entry) {
  const index = entries.findIndex((item) => item.view.anchor === anchor)
  if (index === -1) return false
  entries.splice(index + 1, 0, entry)
  return true
}

export function homeEntries(sections) {
  const entries = (sections ?? [])
    .map((section) => ({ key: section.id, section, view: homeSectionView(section) }))
    .filter((entry) => entry.view)
  insertAfter(entries, HOME_ANCHORS.hero, STATS_ENTRY)
  if (!insertAfter(entries, HOME_ANCHORS.model, LEVELS_ENTRY))
    insertAfter(entries, HOME_ANCHORS.paths, LEVELS_ENTRY)
  return entries
}
