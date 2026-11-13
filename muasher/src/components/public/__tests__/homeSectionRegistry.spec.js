import { describe, expect, it } from 'vitest'
import { homeEntries, homeSectionView } from '@/components/public/homeSectionRegistry'
import HomeTour from '@/components/public/HomeTour.vue'
import HomeJourney from '@/components/public/HomeJourney.vue'
import HomeCtaBanner from '@/components/public/HomeCtaBanner.vue'
import HomeEvaluationPaths from '@/components/public/HomeEvaluationPaths.vue'
import HomeContact from '@/components/public/HomeContact.vue'
import HomeStatsStrip from '@/components/public/HomeStatsStrip.vue'
import HomeLevels from '@/components/public/HomeLevels.vue'

describe('homeSectionView', () => {
  it('resolves the backend section types to their views and anchors', () => {
    expect(homeSectionView({ type: 'paths' })).toMatchObject({
      component: HomeEvaluationPaths,
      anchor: 'paths',
    })
    expect(homeSectionView({ type: 'how_it_works' })).toMatchObject({
      component: HomeTour,
      anchor: 'tour',
    })
    expect(homeSectionView({ type: 'contact' })).toMatchObject({
      component: HomeContact,
      needsSiteSettings: true,
    })
  })

  it('uses the variant when one is registered and falls back to the type otherwise', () => {
    expect(homeSectionView({ type: 'how_it_works', meta: { variant: 'journey' } }).component).toBe(
      HomeJourney,
    )
    expect(homeSectionView({ type: 'rich_text', meta: { variant: 'cta' } }).component).toBe(
      HomeCtaBanner,
    )
    expect(homeSectionView({ type: 'how_it_works', meta: { variant: 'unknown' } }).component).toBe(
      HomeTour,
    )
  })

  it('ignores the sections removed from the approved home page', () => {
    expect(homeSectionView({ type: 'how-it-works' }).component).toBe(HomeTour)
    for (const type of ['rich_text', 'assistant', 'kpi_promo', 'lenses_brief', 'partners'])
      expect(homeSectionView({ type })).toBeNull()
    expect(homeSectionView(null)).toBeNull()
  })
})

describe('homeEntries', () => {
  const sections = [
    { id: 1, type: 'hero' },
    { id: 2, type: 'how_it_works' },
    { id: 3, type: 'paths' },
    { id: 4, type: 'excellence_model' },
    { id: 5, type: 'how_it_works', meta: { variant: 'journey' } },
    { id: 6, type: 'kpi_promo' },
    { id: 7, type: 'contact' },
  ]

  it('adds the stats strip after the hero and the levels after the model', () => {
    const entries = homeEntries(sections)
    expect(entries.map((entry) => entry.key)).toEqual([1, 'stats', 2, 3, 4, 'levels', 5, 7])
    expect(entries[1].view.component).toBe(HomeStatsStrip)
    expect(entries[5].view).toMatchObject({ component: HomeLevels, anchor: 'levels' })
  })

  it('places the levels after the paths when the model section is absent', () => {
    const keys = homeEntries(sections.filter((section) => section.type !== 'excellence_model')).map(
      (entry) => entry.key,
    )
    expect(keys).toEqual([1, 'stats', 2, 3, 'levels', 5, 7])
  })
})
