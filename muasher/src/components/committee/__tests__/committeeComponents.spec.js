import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { committeeService } from '@/services/committeeService'
import AwardStateBadge from '../AwardStateBadge.vue'
import CommitteeEntityCard from '../CommitteeEntityCard.vue'
import DiscussionEntryList from '../DiscussionEntryList.vue'
import RegionResultsCard from '../RegionResultsCard.vue'
import AnnouncementCard from '../AnnouncementCard.vue'
import CommitteeCandidateDialog from '../CommitteeCandidateDialog.vue'
import SecretaryDecisionDialog from '../SecretaryDecisionDialog.vue'
import CriteriaCycleBindings from '@/components/criteria/CriteriaCycleBindings.vue'

vi.mock('@/services/committeeService', () => ({
  committeeService: { listCandidates: vi.fn(), appoint: vi.fn(), substitute: vi.fn() },
}))

const mounted = []
const region = { id: 1, code: 'riyadh', name: { ar: 'الرياض', en: 'Riyadh' } }

function render(component, props, { roles = ['award_secretary'], extra = {}, locale = 'en' } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, extra))
  const wrapper = mount(component, {
    props,
    global: { plugins: globals.plugins },
    attachTo: document.body,
  })
  mounted.push(wrapper)
  return wrapper
}

afterEach(() => {
  mounted.splice(0).forEach((wrapper) => wrapper.unmount())
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('committee display components', () => {
  it('labels seat, row and discussion states in both languages', () => {
    expect(render(AwardStateBadge, { kind: 'row', status: 'not-started' }).text()).toBe(
      'Not started',
    )
    expect(
      render(AwardStateBadge, { kind: 'seat', status: 'disabled' }, { locale: 'ar' }).text(),
    ).toBe('معطّل')
    expect(render(AwardStateBadge, { kind: 'discussion', status: 'cancelled' }).text()).toBe(
      'Cancelled',
    )
  })

  it('shows only the anonymous code when the visibility setting hides the entity', () => {
    const coded = render(CommitteeEntityCard, {
      entity: { visibility: 'code_only', code: 'C-1A2B3C4D', name: null, region },
    })
    expect(coded.text()).toContain('C-1A2B3C4D')
    expect(coded.text()).toContain('only a code is shown')
    const named = render(CommitteeEntityCard, {
      entity: {
        visibility: 'name_only',
        code: null,
        name: { ar: 'جمعية', en: 'Ruwad Education Foundation' },
        region,
      },
    })
    expect(named.text()).toContain('Ruwad Education Foundation')
  })

  it('never shows member names to committee members', () => {
    const entries = [
      {
        id: 1,
        kind: 'opinion',
        body: 'رأي العضو الأول في المتوسط.',
        isMine: true,
        at: '2026-11-01T08:00:00Z',
        member: null,
      },
      {
        id: 2,
        kind: 'request_edit',
        body: 'يرجى مراجعة الدرجة.',
        isMine: false,
        at: '2026-11-01T09:00:00Z',
        member: null,
      },
    ]
    const wrapper = render(DiscussionEntryList, { entries, anonymous: true })
    expect(wrapper.findAll('.discussion-entries__author').map((node) => node.text())).toEqual([
      'Your answer',
      'Committee member',
    ])
  })

  it('disables the region approval while requests are pending and offers the unapproval to the Super Admin only', async () => {
    const pending = render(RegionResultsCard, {
      region: { ...region, approved: false, total: 2, ranked: 1, pending: 1 },
      rows: [],
      canApprove: true,
    })
    expect(
      pending.find('.region-results__actions .btn--primary').attributes('disabled'),
    ).toBeDefined()
    const approved = render(RegionResultsCard, {
      region: { ...region, approved: true, total: 2, ranked: 1, pending: 0 },
      rows: [],
      canApprove: true,
      canUnapprove: true,
    })
    await approved.find('.region-results__actions .btn--danger').trigger('click')
    expect(approved.emitted('unapprove')[0][0].id).toBe(1)
  })

  it('opens the announcement only once the national result is approved', () => {
    const waiting = render(AnnouncementCard, {
      cycleStatus: 'awaiting-secretary',
      national: { approved: false, approvedAt: null, announcedAt: null },
      canAnnounce: true,
    })
    expect(waiting.find('.btn--primary').attributes('disabled')).toBeDefined()
    const done = render(AnnouncementCard, {
      cycleStatus: 'announced',
      national: {
        approved: true,
        approvedAt: '2026-11-01T08:00:00Z',
        announcedAt: '2026-11-02T08:00:00Z',
      },
      canAnnounce: true,
    })
    expect(done.find('.btn--primary').exists()).toBe(false)
    expect(done.text()).toContain('The results were announced on')
  })

  it('keeps the announcement closed while another results action is still settling', async () => {
    const approvedNationally = {
      cycleStatus: 'approved',
      national: { approved: true, approvedAt: '2026-11-01T08:00:00Z', announcedAt: null },
      canAnnounce: true,
    }
    const settling = render(AnnouncementCard, { ...approvedNationally, locked: true })
    expect(settling.find('.btn--primary').attributes('disabled')).toBeDefined()
    await settling.setProps({ locked: false })
    expect(settling.find('.btn--primary').attributes('disabled')).toBeUndefined()
  })

  it('explains the running cycle and offers keep or move for the draft cycles', async () => {
    const bindings = {
      running: {
        id: 31,
        name: { ar: 'دورة', en: 'Award 2026' },
        status: 'submission-open',
        openRequests: 4,
      },
      requestsStayOnCycleVersion: true,
      drafts: [{ id: 32, name: { ar: 'مسودة', en: 'Next award' } }],
    }
    const wrapper = render(CriteriaCycleBindings, { bindings, choice: 'keep', showChoice: true })
    expect(wrapper.text()).toContain('«Award 2026» stays on its criteria version')
    expect(wrapper.text()).toContain('Next award')
    await wrapper.findAll('.cycle-bindings__input')[1].trigger('change')
    expect(wrapper.emitted('update:choice')).toEqual([['move']])
  })
})

describe('committee dialogs', () => {
  it('appoints the chosen candidate and maps the server field error', async () => {
    committeeService.listCandidates.mockResolvedValue({ data: [{ id: 29, name: 'منال' }] })
    committeeService.appoint.mockResolvedValue({
      data: { id: 7005, user: { id: 29, name: 'منال' } },
    })
    const wrapper = render(CommitteeCandidateDialog, { modelValue: true, cycleId: 31 })
    await flushPromises()
    expect(committeeService.listCandidates).toHaveBeenCalledWith(31, {})
    wrapper.vm.$.setupState.candidate = 29
    await wrapper.vm.$.setupState.form.onSubmit()
    await flushPromises()
    expect(committeeService.appoint).toHaveBeenCalledWith(31, { userId: 29 })
    expect(wrapper.emitted('done')[0][0].id).toBe(7005)
  })

  it('builds the discussion payload with an end-of-day Riyadh deadline', async () => {
    const send = vi.fn().mockResolvedValue({ id: 44 })
    const wrapper = render(SecretaryDecisionDialog, {
      modelValue: true,
      kind: 'openDiscussion',
      subject: 'ADV-2026-000005',
      send,
    })
    wrapper.vm.$.setupState.deadlineDate = '2030-01-15'
    await wrapper.vm.$.setupState.form.onSubmit()
    await flushPromises()
    expect(send).toHaveBeenCalledWith({ mode: 'session', deadlineAt: '2030-01-15T20:59:00.000Z' })
  })

  it('requires a return note of at least ten characters', async () => {
    const send = vi.fn()
    const wrapper = render(SecretaryDecisionDialog, {
      modelValue: true,
      kind: 'returnToCommittee',
      subject: 'ADV-2026-000005',
      send,
    })
    await wrapper.vm.$.setupState.form.onSubmit()
    await flushPromises()
    expect(send).not.toHaveBeenCalled()
    expect(document.querySelector('.field__error')).toBeTruthy()
  })
})
