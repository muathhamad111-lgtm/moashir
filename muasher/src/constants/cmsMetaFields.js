import { LANDING_GLYPHS } from './landingGlyphs'

export const CMS_META_LIMITS = Object.freeze({ label: 120, note: 1000, keyWords: 300 })

const label = (key) => ({ key, kind: 'localized', max: CMS_META_LIMITS.label, multiline: false })
const note = (key) => ({ key, kind: 'localized', max: CMS_META_LIMITS.note, multiline: true })
const choice = (key, options) => ({ key, kind: 'choice', options })
const choices = (key, options) => ({ key, kind: 'choices', options })
const integer = (key, min, max) => ({ key, kind: 'integer', min, max })

export const CMS_GLYPHS = Object.freeze(Object.keys(LANDING_GLYPHS))
export const CMS_PATH_KEYS = Object.freeze(['self', 'accredited', 'advanced'])
export const CMS_PATH_TAGS = Object.freeze(['all', 'accredited', 'award'])
export const CMS_SCREENS = Object.freeze([
  'register',
  'choose_path',
  'evidence',
  'review',
  'result',
  'improvement',
])

const HIGHLIGHT = label('highlight')
const GLYPH = choice('glyph', CMS_GLYPHS)

export const CMS_SECTION_META_FIELDS = Object.freeze({
  hero: [HIGHLIGHT, label('scoreCaption')],
  about: [
    HIGHLIGHT,
    { ...label('keyWords'), max: CMS_META_LIMITS.keyWords },
    label('audienceLabel'),
    label('goalsTitle'),
  ],
  excellence_model: [HIGHLIGHT],
  paths: [HIGHLIGHT, label('ideaLabel'), label('costLabel'), label('freeLabel')],
  how_it_works: [
    HIGHLIGHT,
    label('stepLabel'),
    label('tagAll'),
    label('tagAccredited'),
    label('tagAward'),
  ],
  rich_text: [HIGHLIGHT],
  levels: [
    HIGHLIGHT,
    label('resultLabel'),
    note('hint'),
    note('verifyNote'),
    note('settingsNote'),
    integer('sampleScore', 0, 100),
  ],
  bag_promo: [
    HIGHLIGHT,
    label('noticeTitle'),
    note('noticeText'),
    label('howLabel'),
    note('howText'),
  ],
  lenses_brief: [HIGHLIGHT, note('note')],
  leadership_promo: [HIGHLIGHT, label('badgeLabel')],
  kpi_promo: [HIGHLIGHT],
  partners: [HIGHLIGHT],
  contact: [
    HIGHLIGHT,
    label('emailLabel'),
    label('phoneLabel'),
    label('addressLabel'),
    label('socialLabel'),
  ],
  assistant: [HIGHLIGHT, label('inputLabel'), note('fallbackReply')],
})

export const CMS_ITEM_META_FIELDS = Object.freeze({
  about: [GLYPH],
  excellence_model: [GLYPH],
  bag_promo: [GLYPH],
  paths: [choice('path', CMS_PATH_KEYS), note('costNote')],
  how_it_works: [choices('tags', CMS_PATH_TAGS), choice('screen', CMS_SCREENS)],
})

export function metaFieldsFor(sectionType, forItem) {
  const table = forItem ? CMS_ITEM_META_FIELDS : CMS_SECTION_META_FIELDS
  return table[sectionType] ?? []
}
