import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import AssessmentTimeline from '@/components/assessment/AssessmentTimeline.vue'
import AssessmentHistoryItem from '@/components/assessment/AssessmentHistoryItem.vue'
import ResultPendingNotice from '@/components/assessment/ResultPendingNotice.vue'
import SelfAssessmentNotices from '@/components/assessment/self/SelfAssessmentNotices.vue'
import SelfAssessmentActions from '@/components/assessment/self/SelfAssessmentActions.vue'
import SelfSubmitDialog from '@/components/assessment/self/SelfSubmitDialog.vue'
import SelfResultBreakdown from '@/components/assessment/self/SelfResultBreakdown.vue'
import { createTestGlobals } from '@/test-utils'

function mountWith(component, props, { locale = 'en', stubs = {} } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  return mount(component, {
    props,
    global: { ...globals, stubs: { teleport: true, ...stubs } },
    attachTo: document.body,
  })
}

const DRAFT = {
  id: 9002,
  number: 'SLF-2026-000002',
  status: 'draft',
  isFrozen: false,
  criteriaUpdateInProgress: false,
}

describe('AssessmentNotice', () => {
  it('announces live notices as alerts and others as notes, with a text title', () => {
    const live = mountWith(AssessmentNotice, {
      variant: 'warning',
      title: 'Criteria update in progress',
      live: true,
    })
    expect(live.attributes('role')).toBe('alert')
    expect(live.find('.assessment-notice__title').text()).toBe('Criteria update in progress')
    const note = mountWith(AssessmentNotice, { variant: 'frozen', title: 'Frozen' })
    expect(note.attributes('role')).toBe('note')
    expect(note.find('[data-icon="snowflake"]').exists()).toBe(true)
  })
})

describe('SelfAssessmentNotices', () => {
  it('shows the frozen notice and nothing about the criteria update for a frozen draft', () => {
    const wrapper = mountWith(SelfAssessmentNotices, {
      assessment: { ...DRAFT, isFrozen: true, criteriaUpdateInProgress: true },
    })
    expect(wrapper.find('.assessment-notice--frozen').exists()).toBe(true)
    expect(wrapper.find('.assessment-notice--warning').exists()).toBe(false)
  })

  it('offers a check-again path while the criteria update is in progress', async () => {
    const wrapper = mountWith(
      SelfAssessmentNotices,
      { assessment: { ...DRAFT, criteriaUpdateInProgress: true } },
      { locale: 'ar' },
    )
    const notice = wrapper.find('.assessment-notice--warning')
    expect(notice.text()).toContain('جارٍ تحديث المعايير')
    await notice.find('button').trigger('click')
    expect(wrapper.emitted('refresh')).toHaveLength(1)
  })

  it('offers a refresh path on an illegal transition only', async () => {
    const wrapper = mountWith(SelfAssessmentNotices, {
      assessment: DRAFT,
      conflictCode: 'state.transition_illegal',
    })
    await wrapper.find('.assessment-notice--danger button').trigger('click')
    expect(wrapper.emitted('refresh')).toHaveLength(1)
    const frozenConflict = mountWith(SelfAssessmentNotices, {
      assessment: DRAFT,
      conflictCode: 'entity.deleted',
    })
    expect(frozenConflict.find('.assessment-notice--danger').exists()).toBe(false)
  })

  it('lists the kept, reset and added items of the latest move returned by the server', () => {
    const criteriaUpdate = {
      fromVersionId: 3,
      fromVersionNumber: 1,
      toVersionId: 5,
      toVersionNumber: 2,
      movedAt: '2026-11-01T10:00:00Z',
      scope: 'all',
      trigger: 'criteria_edit',
      kept: [{ level: 'sub', code: 'M1.2', label: { ar: 'الحوكمة', en: 'Governance' } }],
      reset: [{ level: 'point', code: 'M1.1.1', label: { ar: 'الرؤية', en: 'Vision' } }],
      added: [{ level: 'point', code: 'M1.1.3', label: null }],
    }
    const wrapper = mountWith(SelfAssessmentNotices, { assessment: DRAFT, criteriaUpdate })
    expect(wrapper.findAll('.rebind-summary__group')).toHaveLength(3)
    expect(wrapper.text()).toContain('moved from criteria version 1 to version 2')
    expect(wrapper.text()).toContain('1 item kept its score')
    expect(wrapper.text()).toContain('1 item needs a new score')
    expect(wrapper.text()).toContain('1 new item')
    expect(wrapper.text()).toContain('Item no longer named in the criteria')
    expect(wrapper.text()).toContain('Nov 1, 2026')
    const none = mountWith(SelfAssessmentNotices, { assessment: DRAFT, criteriaUpdate: null })
    expect(none.find('.rebind-summary').exists()).toBe(false)
  })

  it('names the entity restore as the cause of a move', () => {
    const criteriaUpdate = {
      fromVersionNumber: 1,
      toVersionNumber: 3,
      movedAt: '2026-11-01T10:00:00Z',
      scope: 'reset_open',
      trigger: 'entity_restore',
      kept: [],
      reset: [{ level: 'sub', code: 'M2.1', label: { ar: 'الاستراتيجية', en: 'Strategy' } }],
      added: [],
    }
    const wrapper = mountWith(
      SelfAssessmentNotices,
      { assessment: DRAFT, criteriaUpdate },
      { locale: 'ar' },
    )
    expect(wrapper.text()).toContain('استُعيد حساب الجهة')
    expect(wrapper.text()).not.toMatch(/[\u0660-\u0669]/)
  })
})

describe('SelfAssessmentActions', () => {
  it('keeps saving open but disables submission while the criteria update is in progress', async () => {
    const wrapper = mountWith(SelfAssessmentActions, { submitBlocked: true })
    const [save, submit] = wrapper.findAll('button')
    expect(save.attributes('disabled')).toBeUndefined()
    expect(submit.attributes('disabled')).toBeDefined()
    expect(wrapper.find('.self-actions__hint').text()).toContain('Submission is paused')
    await save.trigger('click')
    expect(wrapper.emitted('save')).toHaveLength(1)
  })

  it('disables both actions on a read-only draft and while busy', () => {
    const readOnly = mountWith(SelfAssessmentActions, { readOnly: true })
    expect(
      readOnly.findAll('button').every((button) => button.attributes('disabled') !== undefined),
    ).toBe(true)
    const busy = mountWith(SelfAssessmentActions, { saving: true })
    expect(busy.findAll('button')[1].attributes('disabled')).toBeDefined()
  })
})

describe('SelfSubmitDialog', () => {
  it('asks one short question with the request number and a send / cancel pair', async () => {
    const wrapper = mountWith(SelfSubmitDialog, { modelValue: true, number: 'SLF-2026-000002' })
    await flushPromises()
    expect(wrapper.find('[role="alertdialog"] .modal__title').text()).toBe(
      'Submit the self assessment?',
    )
    expect(wrapper.find('.modal__message').text()).toBe('SLF-2026-000002')
    expect(wrapper.findAll('li')).toHaveLength(0)
    const buttons = wrapper.findAll('.modal__footer .btn')
    expect(buttons.map((button) => button.text())).toEqual(['Send', 'Cancel'])
    await buttons[0].trigger('click')
    expect(wrapper.emitted('confirm')).toHaveLength(1)
  })
})

describe('AssessmentTimeline and history item', () => {
  const RESULT = {
    id: 9001,
    number: 'SLF-2026-000001',
    path: 'self',
    status: 'result-available',
    isOpen: false,
    isFrozen: false,
    result: { total: '6329.17', maxTotal: '8200.00', percent: '77.18' },
    createdAt: '2026-06-20T07:00:00Z',
    submittedAt: '2026-06-20T07:42:00Z',
    completedAt: '2026-06-20T07:42:00Z',
  }

  const HISTORY = [
    { from: null, to: 'draft', verb: 'start', at: '2026-06-20T07:00:00Z', actor: 'entity' },
    {
      from: 'draft',
      to: 'submitted',
      verb: 'submit',
      at: '2026-06-20T07:42:00Z',
      actor: 'staff',
      actorStaff: {
        name: 'منيرة بنت خالد العتيبي',
        roles: [{ key: 'system_admin', label: { ar: 'مدير النظام', en: 'System Administrator' } }],
      },
    },
    {
      from: 'submitted',
      to: 'result-available',
      verb: 'compute_result',
      at: '2026-06-20T07:42:00Z',
      actor: 'system',
    },
  ]

  it('lists every status change from status_history with its actor, in Asia/Riyadh with Western digits', () => {
    const wrapper = mountWith(AssessmentTimeline, { history: HISTORY }, { locale: 'ar' })
    const steps = wrapper.findAll('.assessment-timeline__step')
    expect(steps).toHaveLength(3)
    expect(steps[0].find('time').text()).toMatch(/10:00/)
    expect(steps[0].find('.assessment-timeline__actor').text()).toBe('الجهة')
    expect(steps[1].text()).toContain('منيرة بنت خالد العتيبي')
    expect(steps[1].text()).toContain('مدير النظام')
    expect(steps[2].find('.assessment-timeline__actor').text()).toBe('النظام')
    expect(wrapper.text()).not.toMatch(/[\u0660-\u0669]/)
  })

  it('shows a generic staff label when the staff member is hidden or the viewer is the entity', () => {
    const entityView = [
      {
        from: 'draft',
        to: 'submitted',
        verb: 'submit',
        at: '2026-06-20T07:42:00Z',
        actor: 'staff',
      },
    ]
    expect(mountWith(AssessmentTimeline, { history: entityView }).text()).toContain('Staff member')
    const hidden = [{ ...entityView[0], actorStaff: { name: null, roles: [] } }]
    expect(mountWith(AssessmentTimeline, { history: hidden }).text()).toContain('Staff member')
    const unknownVerb = [
      {
        from: 'draft',
        to: 'submitted',
        verb: 'return',
        at: '2026-06-20T07:42:00Z',
        actor: 'system',
      },
    ]
    expect(mountWith(AssessmentTimeline, { history: unknownVerb }).text()).toContain(
      'Status changed to Submitted',
    )
  })

  it('links a result to its result page and an open draft to the form, never a frozen one', () => {
    const result = mountWith(AssessmentHistoryItem, { item: RESULT })
    expect(result.text()).toContain('Scored 77.18%')
    expect(result.find('a[href="/assessment/self/result/9001"]').exists()).toBe(true)
    const draft = mountWith(AssessmentHistoryItem, {
      item: { ...RESULT, status: 'draft', isOpen: true, result: null },
    })
    expect(draft.find('a[href="/assessment/self"]').exists()).toBe(true)
    const frozen = mountWith(AssessmentHistoryItem, {
      item: { ...RESULT, status: 'draft', isOpen: true, isFrozen: true, result: null },
    })
    expect(frozen.find('a[href="/assessment/self"]').exists()).toBe(false)
    expect(frozen.find('.frozen-badge').exists()).toBe(true)
  })
})

describe('result components', () => {
  it('shows the not-issued placeholder with the request status (Q-94)', () => {
    const wrapper = mountWith(ResultPendingNotice, {
      result: { id: 9002, status: 'draft', available: false },
    })
    expect(wrapper.text()).toContain('The result is not issued yet')
    expect(wrapper.find('.badge').text()).toBe('Draft')
  })

  it('renders mains, subs and points with the figures as returned', () => {
    const result = {
      id: 9001,
      main: [
        {
          criterionId: 1,
          code: 'M1',
          name: { ar: 'القيادة', en: 'Leadership' },
          weight: '1000.00',
          earned: '769.17',
          percent: '76.92',
        },
      ],
      sub: [
        {
          subId: 11,
          mainCriterionId: 1,
          code: 'M1.1',
          name: { ar: 'التوجه', en: 'Direction' },
          rawValue: null,
          weight: '400.00',
          earned: '326.67',
          percent: '81.67',
        },
      ],
      points: [
        {
          pointId: 111,
          subCriterionId: 11,
          code: 'M1.1.1',
          description: { ar: 'وثيقة', en: 'Document' },
          rawValue: '8.50',
          weight: '133.34',
          earned: '113.34',
          percent: '85.00',
        },
      ],
    }
    const wrapper = mountWith(SelfResultBreakdown, { result })
    expect(wrapper.find('.self-result__main-earned').text()).toBe('769.17 / 1000.00')
    expect(wrapper.find('.self-result__bar').attributes('value')).toBe('76.92')
    expect(wrapper.find('.self-result__point').text()).toContain('Score 8.50')
    expect(wrapper.find('.self-result__point').text()).toContain('85.00%')
  })
})
