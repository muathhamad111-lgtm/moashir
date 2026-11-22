import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { contentService } from '@/services/contentService'
import { servicePricesService } from '@/services/servicePricesService'
import { kpisService } from '@/services/kpisService'
import LandingHero from '../LandingHero.vue'
import LandingStats from '../LandingStats.vue'
import LandingAbout from '../LandingAbout.vue'
import LandingModel from '../LandingModel.vue'
import LandingPaths from '../LandingPaths.vue'
import LandingJourney from '../LandingJourney.vue'
import LandingBand from '../LandingBand.vue'
import LandingLevels from '../LandingLevels.vue'
import LandingKit from '../LandingKit.vue'
import LandingLenses from '../LandingLenses.vue'
import LandingLeaders from '../LandingLeaders.vue'
import LandingPartners from '../LandingPartners.vue'
import LandingFinal from '../LandingFinal.vue'
import LandingContact from '../LandingContact.vue'
import {
  FEATURED,
  LEVELS,
  PRICES,
  landingSection,
  mountLanding,
  stubBrowser,
} from './landingTestKit'

vi.mock('@/services/contentService', () => ({
  contentService: { getHome: vi.fn(), getCertificateLevels: vi.fn(), getModelCriteria: vi.fn() },
}))
vi.mock('@/services/servicePricesService', () => ({
  servicePricesService: { getServicePrices: vi.fn() },
}))
vi.mock('@/services/kpisService', () => ({ kpisService: { getFeatured: vi.fn() } }))

const MODEL_CRITERIA = [
  { id: 1, code: 'M1', order: 1, group: 'enablers', name: { ar: 'القيادة', en: 'Leadership' } },
  { id: 2, code: 'M2', order: 2, group: 'enablers', name: { ar: 'التخطيط', en: 'Planning' } },
  {
    id: 3,
    code: 'M3',
    order: 3,
    group: 'creativity_learning',
    name: { ar: 'الابتكار', en: 'Innovation' },
  },
  { id: 4, code: 'M4', order: 4, group: 'results', name: { ar: 'النتائج', en: 'Outcomes' } },
]

async function mounted(component, key, options = {}) {
  const section = options.section ?? landingSection(key)
  const result = mountLanding(component, { props: { section }, ...options })
  await flushPromises()
  return { ...result, section }
}

beforeEach(() => {
  stubBrowser()
  contentService.getCertificateLevels.mockResolvedValue({ data: { levels: LEVELS } })
  contentService.getModelCriteria.mockResolvedValue({ data: MODEL_CRITERIA })
  servicePricesService.getServicePrices.mockResolvedValue({ data: PRICES })
  kpisService.getFeatured.mockResolvedValue({ data: FEATURED, meta: { minimum: 3 } })
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('LandingHero', () => {
  it('renders the CMS copy, both calls to action and one seal per configured level', async () => {
    const { wrapper, section } = await mounted(LandingHero, 'hero')
    expect(wrapper.find('h1').text()).toContain(section.title.ar)
    expect(wrapper.find('h1 span').text()).toBe(section.meta.highlight.ar)
    expect(wrapper.find('.lp-hero__chip').text()).toBe(section.meta.eyebrow.ar)
    const links = wrapper.findAll('.lp-hero__copy a')
    expect(links).toHaveLength(2)
    expect(links[0].attributes('href')).toBe(section.ctaUrl)
    expect(wrapper.findAll('.lp-seal')).toHaveLength(4)
    expect(wrapper.find('.lp-seal figcaption').text()).toContain('المستوى البرونزي')
  })

  it('switches to English copy', async () => {
    const { wrapper, section } = await mounted(LandingHero, 'hero', { locale: 'en' })
    expect(wrapper.find('h1').text()).toContain(section.title.en)
    expect(wrapper.find('.lp-seal figcaption').text()).toContain('Bronze level')
  })

  it('uses an uploaded trophy image when the section has one', async () => {
    const section = {
      ...landingSection('hero'),
      image: { fileId: 'f-1', url: '/api/v1/content/files/f-1' },
    }
    const { wrapper } = await mounted(LandingHero, 'hero', { section })
    const image = wrapper.find('.lp-hero__glass img')
    expect(image.attributes('src')).toBe('/api/v1/content/files/f-1')
    expect(image.attributes('srcset')).toBeUndefined()
  })
})

describe('LandingStats', () => {
  it('shows the featured indicators and the link from the CMS', async () => {
    const { wrapper, section } = await mounted(LandingStats, 'stats')
    expect(wrapper.findAll('.lp-stat')).toHaveLength(3)
    expect(wrapper.text()).toContain('جهة مسجّلة')
    expect(wrapper.find('.lp-stats__note a').text()).toBe(section.ctaLabel.ar)
  })

  it('stays hidden when no indicator is featured', async () => {
    kpisService.getFeatured.mockResolvedValue({ data: [], meta: { minimum: 3 } })
    const { wrapper } = await mounted(LandingStats, 'stats')
    expect(wrapper.find('.lp-stats').attributes('hidden')).toBeDefined()
  })
})

describe('LandingAbout', () => {
  it('splits the manifesto into words and lights the CMS key words', async () => {
    const { wrapper, section } = await mounted(LandingAbout, 'about')
    const words = wrapper.findAll('.lp-manifesto__word')
    expect(words.length).toBe(section.description.ar.trim().split(/\s+/).length)
    expect(wrapper.findAll('.lp-manifesto__word.is-key').length).toBeGreaterThan(0)
    expect(wrapper.findAll('.lp-manifesto__word.is-lit')).toHaveLength(words.length)
  })

  it('renders the audience tiles and the numbered goals from the items', async () => {
    const { wrapper, section } = await mounted(LandingAbout, 'about')
    const audience = section.items.filter((item) => item.meta.role === 'audience')
    const goals = section.items.filter((item) => item.meta.role === 'goal')
    expect(wrapper.findAll('.lp-who')).toHaveLength(audience.length)
    expect(wrapper.findAll('.lp-goal')).toHaveLength(goals.length)
    expect(wrapper.find('.lp-goal__num').text()).toBe('01')
    expect(wrapper.find('.lp-serves__label').text()).toBe(section.meta.audienceLabel.ar)
  })
})

describe('LandingModel', () => {
  it('renders every stage with its glyph', async () => {
    const { wrapper, section } = await mounted(LandingModel, 'model')
    expect(wrapper.findAll('.lp-cycle__step')).toHaveLength(section.items.length)
    expect(wrapper.findAll('.lp-glyph')).toHaveLength(section.items.length)
    expect(wrapper.find('h2').text()).toContain(section.meta.highlight.ar)
  })

  it('lists the published main criteria by group under the cycle', async () => {
    const { wrapper } = await mounted(LandingModel, 'model')
    const groups = wrapper.findAll('.lp-model-criteria__group')

    expect(wrapper.find('#lp-model-criteria-title').text()).toBe(
      'المعايير الرئيسية المنشورة في النموذج',
    )
    expect(groups).toHaveLength(3)
    expect(groups[0].find('h4').text()).toBe('الممكّنات')
    expect(groups[0].findAll('.lp-tag').map((tag) => tag.text())).toEqual(['القيادة', 'التخطيط'])
    expect(wrapper.findAll('.lp-cycle .lp-tag')).toHaveLength(0)
  })

  it('names the groups and criteria in English', async () => {
    const { wrapper } = await mounted(LandingModel, 'model', { locale: 'en' })
    const groups = wrapper.findAll('.lp-model-criteria__group')

    expect(groups[0].find('h4').text()).toBe('Enablers')
    expect(groups[2].findAll('.lp-tag').map((tag) => tag.text())).toEqual(['Outcomes'])
  })

  it('shows nothing extra when the published criteria cannot be loaded', async () => {
    contentService.getModelCriteria.mockRejectedValue(new Error('offline'))
    const { wrapper, section } = await mounted(LandingModel, 'model')

    expect(wrapper.find('.lp-model-criteria').exists()).toBe(false)
    expect(wrapper.findAll('.lp-cycle__step')).toHaveLength(section.items.length)
  })

  it('reserves the space with a busy state while the criteria load', async () => {
    contentService.getModelCriteria.mockReturnValue(new Promise(() => {}))
    const { wrapper } = await mounted(LandingModel, 'model')
    const block = wrapper.find('.lp-model-criteria')

    expect(block.classes()).toContain('is-loading')
    expect(block.attributes('aria-busy')).toBe('true')
    expect(block.find('.lp-model-criteria__groups').exists()).toBe(false)
  })
})

describe('LandingPaths', () => {
  it('shows free, priced and note costs from the prices endpoint', async () => {
    const { wrapper, section } = await mounted(LandingPaths, 'paths')
    const cards = wrapper.findAll('.lp-path')
    expect(cards).toHaveLength(3)
    expect(cards[0].classes()).toContain('lp-path--featured')
    expect(cards[0].find('.lp-path__cost b').text()).toBe(section.meta.freeLabel.ar)
    expect(cards[1].find('.lp-path__cost').text()).toMatch(/1,?000/)
    expect(cards[1].find('.lp-path__vat').text()).toContain('15')
    expect(cards[1].find('.lp-path__vat').text()).toMatch(/1,?150/)
    expect(cards[2].find('.lp-path__cost b').text()).toBe(section.items[2].meta.costNote.ar)
  })

  it('falls back to the CMS notes when prices cannot be loaded', async () => {
    servicePricesService.getServicePrices.mockRejectedValue(new Error('offline'))
    const { wrapper, section } = await mounted(LandingPaths, 'paths')
    const notes = wrapper.findAll('.lp-path__cost b').map((node) => node.text())
    expect(notes[1]).toBe(section.items[1].meta.costNote.ar)
  })
})

describe('LandingJourney', () => {
  it('renders each step with its tags and illustration screen', async () => {
    const { wrapper, section } = await mounted(LandingJourney, 'journey')
    const rows = wrapper.findAll('.lp-jr')
    expect(rows).toHaveLength(section.items.length)
    expect(rows[0].find('.lp-jr__step').text()).toContain(section.meta.stepLabel.ar)
    expect(rows[0].find('.lp-tag').text()).toBe(section.meta.tagAll.ar)
    expect(rows[3].findAll('.lp-tag')).toHaveLength(2)
    expect(wrapper.findAll('.lp-screen')).toHaveLength(section.items.length)
    expect(wrapper.find('.lp-screen__qr path').attributes('d')).toBeTruthy()
  })
})

describe('LandingBand and LandingFinal', () => {
  it('renders the mid call to action with both buttons', async () => {
    const { wrapper, section } = await mounted(LandingBand, 'mid_cta')
    expect(wrapper.find('h2').text()).toBe(section.title.ar)
    expect(wrapper.findAll('a')).toHaveLength(2)
    expect(wrapper.find('[data-lp-deep]').exists()).toBe(true)
  })

  it('renders the closing call to action', async () => {
    const { wrapper, section } = await mounted(LandingFinal, 'start')
    expect(wrapper.find('h2').text()).toBe(section.title.ar)
    expect(wrapper.find('a').attributes('href')).toBe(section.ctaUrl)
  })
})

describe('LandingLevels', () => {
  it('starts at the CMS sample score and names its tier', async () => {
    const { wrapper, section } = await mounted(LandingLevels, 'levels')
    expect(wrapper.find('.lp-lv__num span').text()).toBe(String(section.meta.sampleScore))
    expect(wrapper.find('.lp-lv__name').text()).toBe('المستوى الذهبي')
    expect(wrapper.find('#lp-lv-range').attributes('aria-valuetext')).toContain('المستوى الذهبي')
  })

  it('moves to a tier from its button and from the keyboard range', async () => {
    const { wrapper } = await mounted(LandingLevels, 'levels')
    const buttons = wrapper.findAll('.lp-lv__tiers button')
    expect(buttons).toHaveLength(5)
    await buttons[1].trigger('click')
    expect(wrapper.find('.lp-lv__name').text()).toBe('المستوى البرونزي')
    expect(buttons[1].attributes('aria-pressed')).toBe('true')
    await wrapper.find('#lp-lv-range').setValue('95')
    expect(wrapper.find('.lp-lv__name').text()).toBe('المستوى الماسي')
    await wrapper.find('#lp-lv-range').setValue('10')
    expect(wrapper.find('.lp-lv__name').text()).toBe('شهادة مشاركة')
  })

  it('labels the ranges from the configured thresholds', async () => {
    const { wrapper } = await mounted(LandingLevels, 'levels')
    const ranges = wrapper.findAll('.lp-lv__tiers span').map((node) => node.text())
    expect(ranges).toEqual(['أقل من 60', '60–70', '70–80', '80–90', '90 فأكثر'])
  })
})

describe('LandingKit', () => {
  it('shows the notice, steps, tools and how-it-works from the CMS', async () => {
    const { wrapper, section } = await mounted(LandingKit, 'kit')
    expect(wrapper.find('.lp-kit__pre b').text()).toBe(section.meta.noticeTitle.ar)
    expect(wrapper.findAll('.lp-kit__steps li')).toHaveLength(4)
    expect(wrapper.findAll('.lp-tool')).toHaveLength(4)
    expect(wrapper.find('.lp-kit__how').text()).toContain(section.meta.howText.ar)
  })

  it('shows the whole gap map at once with reduced motion and lets a step be chosen', async () => {
    const { wrapper } = await mounted(LandingKit, 'kit')
    expect(wrapper.find('.lp-kit__stage').classes()).toEqual(
      expect.arrayContaining(['is-s0', 'is-s1', 'is-s2', 'is-s3']),
    )
    await wrapper.findAll('.lp-kit__steps button')[1].trigger('click')
    expect(wrapper.find('.lp-kit__stage').classes()).not.toContain('is-s2')
    expect(wrapper.findAll('.lp-kit__steps button')[1].attributes('aria-pressed')).toBe('true')
  })
})

describe('LandingLenses', () => {
  it('selects a lens from the list and shows only its description', async () => {
    const { wrapper, section } = await mounted(LandingLenses, 'lenses')
    const buttons = wrapper.findAll('.lp-lenses__list button')
    expect(buttons).toHaveLength(section.items.length)
    expect(wrapper.find('.lp-lenses__center-name').text()).toBe(section.items[0].title.ar)
    await buttons[2].trigger('click')
    expect(buttons[2].attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('.lp-lenses__center-name').text()).toBe(section.items[2].title.ar)
    expect(wrapper.findAll('.lp-lenses__arc.is-on')).toHaveLength(1)
  })

  it('selects a lens from the ring', async () => {
    const { wrapper, section } = await mounted(LandingLenses, 'lenses')
    await wrapper.findAll('.lp-lenses__arc')[4].trigger('click')
    expect(wrapper.find('.lp-lenses__center-name').text()).toBe(section.items[4].title.ar)
  })
})

describe('LandingLeaders and LandingPartners', () => {
  it('shows the leaders badge from the CMS', async () => {
    const { wrapper, section } = await mounted(LandingLeaders, 'leaders')
    expect(wrapper.find('.lp-leaders__soon').text()).toBe(section.meta.badgeLabel.ar)
  })

  it('renders nothing until a partner has an uploaded logo', async () => {
    const { wrapper } = await mounted(LandingPartners, 'partners')
    expect(wrapper.find('.lp-partners').exists()).toBe(false)
  })

  it('renders uploaded logos with the partner name as alternative text', async () => {
    const section = landingSection('partners')
    section.items[0].image = { fileId: 'logo', url: '/api/v1/content/files/logo' }
    section.items[0].ctaUrl = 'https://partner.example.sa'
    const { wrapper } = await mounted(LandingPartners, 'partners', { section })
    const logo = wrapper.find('.lp-partners__logo img')
    expect(logo.attributes('alt')).toBe(section.items[0].title.ar)
    expect(logo.attributes('loading')).toBe('lazy')
    const link = wrapper.find('a.lp-partners__logo')
    expect(link.attributes('rel')).toContain('noopener')
    expect(link.attributes('target')).toBe('_blank')
    expect(link.find('.lp-sr-only').text()).toContain('تبويب جديد')
  })

  it('shows a logo without a link as a plain logo, and only the partners that have one', async () => {
    const section = landingSection('partners')
    section.items[1].image = { fileId: 'plain', url: '/api/v1/content/files/plain' }
    section.items[1].ctaUrl = null
    const { wrapper } = await mounted(LandingPartners, 'partners', { section })
    expect(wrapper.findAll('.lp-partners__item')).toHaveLength(1)
    expect(wrapper.find('a.lp-partners__logo').exists()).toBe(false)
    expect(wrapper.find('span.lp-partners__logo img').attributes('alt')).toBe(
      section.items[1].title.ar,
    )
    expect(wrapper.find('.lp-sr-only').exists()).toBe(false)
  })
})

describe('LandingContact', () => {
  it('shows the contact data from the site settings and the CMS labels', async () => {
    const { wrapper, section } = await mounted(LandingContact, 'contact')
    const text = wrapper.find('.lp-contact__list').text()
    expect(text).toContain(section.meta.emailLabel.ar)
    expect(text).toContain('info@moashir.example.sa')
    expect(wrapper.find('a[href^="tel:"]').attributes('href')).toBe('tel:+966110000000')
    expect(wrapper.findAll('.lp-contact__social a')).toHaveLength(3)
    expect(wrapper.find('form').exists()).toBe(true)
  })
})
