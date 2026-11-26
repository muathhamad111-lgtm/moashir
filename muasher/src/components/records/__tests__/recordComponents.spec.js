import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import RecordSummary from '../RecordSummary.vue'
import RecordIdentity from '../RecordIdentity.vue'
import RecordStatus from '../RecordStatus.vue'
import RecordGroup from '../RecordGroup.vue'
import RecordFactList from '../RecordFactList.vue'
import RecordCardHeader from '../RecordCardHeader.vue'

// The staff record summary (request page v4.1, layout A) and its parts.
function render(component, options = {}, locale = 'en') {
  return mount(component, { ...options, global: createTestGlobals({ locale }) })
}

describe('RecordSummary', () => {
  it('puts the way back at the start of the row above and the actions at its end', () => {
    const wrapper = render(RecordSummary, {
      props: { backTo: { path: '/admin/requests' }, backLabel: 'Back to the requests' },
      slots: {
        actions: '<button class="btn">Open the review</button>',
        identity: '<h1>ACC-1</h1>',
        status: '<p class="status">Draft</p>',
        groups: '<section class="record-group">Dates</section>',
      },
    })
    const bar = wrapper.find('.record-summary__bar')
    expect(bar.find('.record-summary__back').text()).toBe('Back to the requests')
    expect(bar.find('.record-summary__actions button').text()).toBe('Open the review')
    expect(wrapper.find('.record-summary__top h1').text()).toBe('ACC-1')
    expect(wrapper.find('.record-summary__top .status').exists()).toBe(true)
    expect(wrapper.find('.record-summary__groups .record-group').exists()).toBe(true)
  })

  it('leaves out the actions and the groups when there are none', () => {
    const wrapper = render(RecordSummary, {
      props: { backTo: { path: '/' }, backLabel: 'Back' },
      slots: { identity: '<h1>KIT</h1>' },
    })
    expect(wrapper.find('.record-summary__actions').exists()).toBe(false)
    expect(wrapper.find('.record-summary__groups').exists()).toBe(false)
  })
})

describe('RecordIdentity', () => {
  it('shows the kind, the number as an isolated left-to-right title and the subject', () => {
    const wrapper = render(RecordIdentity, {
      props: { kind: 'Accredited assessment', title: 'ACC-2026-000008', titleIsCode: true },
      slots: { mark: '<span class="mark" />', subject: 'Ehsan Society' },
    })
    expect(wrapper.find('.record-identity__kind').text()).toBe('Accredited assessment')
    expect(wrapper.find('h1 bdi').attributes('dir')).toBe('ltr')
    expect(wrapper.find('.record-identity__subject').text()).toBe('Ehsan Society')
  })

  it('writes a plain title without isolation', () => {
    const wrapper = render(RecordIdentity, { props: { title: 'Manage the Kit' } })
    expect(wrapper.find('h1 bdi').exists()).toBe(false)
    expect(wrapper.find('.record-identity__kind').exists()).toBe(false)
  })
})

describe('RecordStatus', () => {
  it('shows the status once with the date it entered it, isolated left to right', () => {
    const wrapper = render(
      RecordStatus,
      {
        props: { status: 'under-review', since: '2026-10-27T06:00:00Z' },
        slots: { default: 'Frozen' },
      },
      'ar',
    )
    expect(wrapper.find('.record-status__label').text()).toBe('الحالة')
    expect(wrapper.findAll('.badge')).toHaveLength(1)
    expect(wrapper.find('.record-status__since').text()).toBe('منذ 2026-10-27')
    expect(wrapper.find('.record-status__since bdi').attributes('dir')).toBe('ltr')
    expect(wrapper.text()).toContain('Frozen')
  })

  it('has no «since» line without a date', () => {
    const wrapper = render(RecordStatus, { props: { status: 'draft' } })
    expect(wrapper.find('.record-status__since').exists()).toBe(false)
  })
})

describe('RecordGroup and RecordFactList', () => {
  it('lists each fact as a label above its value in the requested number of columns', () => {
    const wrapper = render(RecordGroup, {
      props: { title: 'Dates' },
      slots: { default: '<p class="facts">facts</p>' },
    })
    expect(wrapper.find('h2').text()).toBe('Dates')
    expect(wrapper.find('.facts').exists()).toBe(true)
    const list = render(RecordFactList, {
      props: {
        columns: 3,
        facts: [
          { key: 'submittedAt', label: 'Submitted' },
          { key: 'dueAt', label: 'Due' },
        ],
      },
      slots: { default: '<template #default="params">value-{{ params.fact }}</template>' },
    })
    expect(list.attributes('style')).toContain('--record-fact-columns: 3')
    expect(list.findAll('.record-facts__term').map((term) => term.text())).toEqual([
      'Submitted',
      'Due',
    ])
    expect(list.findAll('.record-facts__value').map((value) => value.text())).toEqual([
      'value-submittedAt',
      'value-dueAt',
    ])
  })
})

describe('RecordCardHeader', () => {
  it('puts the title at the start and the card actions in a named group at the end', () => {
    const wrapper = render(RecordCardHeader, {
      props: { title: 'Certificate', actionsLabel: 'Certificate actions' },
      slots: { actions: '<button>Download</button><button>Revoke</button>' },
    })
    expect(wrapper.find('h2').text()).toBe('Certificate')
    const actions = wrapper.find('.record-card-header__actions')
    expect(actions.attributes('role')).toBe('group')
    expect(actions.attributes('aria-label')).toBe('Certificate actions')
    expect(actions.findAll('button')).toHaveLength(2)
  })

  it('renders the title alone when the card has no actions', () => {
    const wrapper = render(RecordCardHeader, { props: { title: 'Payment' } })
    expect(wrapper.find('.record-card-header__actions').exists()).toBe(false)
  })
})
