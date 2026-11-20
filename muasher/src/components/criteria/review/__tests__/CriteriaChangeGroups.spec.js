import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CriteriaChangeGroups from '@/components/criteria/review/CriteriaChangeGroups.vue'
import { createTestGlobals } from '@/test-utils'

// The API sends the field names as values and the before/after maps with camelCase keys.
const CHANGES = [
  {
    nodeType: 'point',
    code: 'M1.1.2',
    change: 'modified',
    fields: ['weight', 'scoring_type'],
    before: { weight: '133.33', scoringType: 'score' },
    after: { weight: '150.00', scoringType: 'achievement' },
    scoreAffecting: true,
    label: { ar: 'ربط الأهداف بالرؤية', en: 'Goals linked to the vision' },
  },
  {
    nodeType: 'sub',
    code: 'M1.3',
    change: 'modified',
    fields: ['name'],
    before: { name: { ar: 'القيادة بالقدوة', en: 'Leading by example' } },
    after: { name: { ar: 'ثقافة التميز', en: 'Excellence culture' } },
    scoreAffecting: false,
    label: { ar: 'ثقافة التميز', en: 'Excellence culture' },
  },
  {
    nodeType: 'sub',
    code: 'M2.4',
    change: 'added',
    fields: [],
    before: {},
    after: {},
    scoreAffecting: true,
    label: { ar: 'إدارة المخاطر', en: 'Risk management' },
  },
]

function mountGroups(locale = 'en') {
  return mount(CriteriaChangeGroups, {
    props: {
      changes: CHANGES,
      mainNames: new Map([
        ['M1', { ar: 'القيادة الإدارية', en: 'Administrative Leadership' }],
        ['M2', { ar: 'التخطيط', en: 'Planning' }],
      ]),
    },
    global: createTestGlobals({ locale }),
  })
}

const rowOf = (wrapper, code) =>
  wrapper.findAll('.criteria-change').find((row) => row.text().includes(code))

describe('CriteriaChangeGroups', () => {
  it('puts each change under its main criterion with the group size', () => {
    const wrapper = mountGroups()
    const heads = wrapper.findAll('.criteria-change-group__head').map((head) => head.text())
    expect(heads[0]).toContain('Administrative Leadership')
    expect(heads[0]).toContain('2 changes')
    expect(heads[1]).toContain('Planning')
    expect(heads[1]).toContain('1 change')
  })

  it('shows each changed field as before → after, in words', () => {
    const row = rowOf(mountGroups(), 'M1.1.2')
    const fields = row.findAll('.criteria-change__field')
    expect(fields[0].find('dt').text()).toBe('weight')
    expect(fields[0].find('.criteria-change__before').text()).toBe('133.33')
    expect(fields[0].find('.criteria-change__after').text()).toBe('150.00')
    expect(fields[1].find('.criteria-change__after').text()).toBe('Achieved / not achieved')
    expect(row.find('.criteria-change__tags').text()).toBe('Needs a new score')
  })

  it('marks a renamed item as keeping its score and a new item as new', () => {
    const wrapper = mountGroups()
    expect(rowOf(wrapper, 'M1.3').find('.criteria-change__tags').text()).toContain(
      'Keeps its score',
    )
    expect(rowOf(wrapper, 'M1.3').find('.criteria-change__tags').text()).toContain('Text only')
    const added = rowOf(wrapper, 'M2.4')
    expect(added.find('.criteria-change__whole').text()).toBe('New in this version.')
    expect(added.find('.criteria-change__fields').exists()).toBe(false)
  })

  it('renders the Arabic texts', () => {
    const row = rowOf(mountGroups('ar'), 'M1.3')
    expect(row.find('.criteria-change__before').text()).toBe('القيادة بالقدوة')
    expect(row.find('.criteria-change__tags').text()).toContain('يحتفظ بدرجته')
  })

  it('shows the language that changed when only one of the two names was edited', () => {
    const wrapper = mount(CriteriaChangeGroups, {
      props: {
        changes: [
          {
            nodeType: 'sub',
            code: 'M1.1',
            change: 'modified',
            fields: ['name'],
            before: { name: { ar: 'التخطيط', en: 'Planning' } },
            after: { name: { ar: 'التخطيط', en: 'Strategic planning' } },
            scoreAffecting: false,
            label: { ar: 'التخطيط', en: 'Strategic planning' },
          },
        ],
      },
      global: createTestGlobals({ locale: 'ar' }),
    })
    const after = wrapper.find('.criteria-change__after bdi')
    expect(after.text()).toBe('Strategic planning')
    expect(after.attributes('dir')).toBe('ltr')
    expect(wrapper.find('.criteria-change__before').text()).toBe('Planning')
  })
})
