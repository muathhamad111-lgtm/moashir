import { describe, expect, it, vi } from 'vitest'
import { withSetup } from '@/test-utils/withSetup'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { useCommitteeNotes } from '@/composables/useCommitteeNotes'
import { useUserActions } from '@/composables/useUserActions'
import { useCycleBindings } from '@/composables/useCycleBindings'
import { criteriaService } from '@/services/criteriaService'
import { decisionPayload } from '@/utils/award/secretaryDecision'
import {
  changedAssessmentSettings,
  settingsFormValues,
} from '@/utils/accredited/assessmentSettingsForm'
import { committeeEntityLabel } from '@/utils/award/committeeEntity'

vi.mock('@/services/criteriaService', () => ({ criteriaService: { checkVersion: vi.fn() } }))

describe('committee notes draft', () => {
  it('sends only the changed sub notes, clears emptied ones and the general note when edited', () => {
    const { api } = withSetup(() => useCommitteeNotes())
    api.resetFrom({ subs: { 12: 'ملاحظة أولى', 13: 'ملاحظة ثانية' }, general: '' })
    expect(api.dirty.value).toBe(false)
    api.setNote(12, 'ملاحظة أولى')
    api.setNote(13, '  ')
    api.setNote(14, 'ملاحظة جديدة')
    expect(api.changedPayload()).toEqual({ notes: { 13: null, 14: 'ملاحظة جديدة' } })
    api.setGeneral('ملاحظة عامة')
    expect(api.changedPayload().generalNote).toBe('ملاحظة عامة')
    expect(api.dirty.value).toBe(true)
  })
})

describe('user account actions', () => {
  it('offers the verbs that fit the status and never acts on the own account', () => {
    const { api, globals } = withSetup(() => useUserActions({ onChanged: vi.fn() }))
    useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin'], { id: 1 }))
    const keys = (user) => api.actionsFor(user).map((action) => action.key)
    expect(keys({ id: 2, status: 'active', mfaEnabled: true })).toEqual([
      'edit',
      'suspend',
      'resetMfa',
    ])
    expect(keys({ id: 3, status: 'suspended', mfaEnabled: false })).toEqual(['edit', 'activate'])
    expect(keys({ id: 4, status: 'locked', mfaEnabled: true })).toEqual([
      'edit',
      'unlock',
      'resetMfa',
    ])
    expect(keys({ id: 5, status: 'expired', mfaEnabled: false })).toEqual([
      'edit',
      'resendInvitation',
    ])
    expect(keys({ id: 1, status: 'active', mfaEnabled: true })).toEqual(['edit'])
  })

  it('offers the path access editors to assessors and followers other than the own account', () => {
    const { api, globals } = withSetup(() => useUserActions({ onChanged: vi.fn() }))
    useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin'], { id: 1 }))
    const keys = (user) => api.actionsFor(user).map((action) => action.key)
    expect(keys({ id: 10, status: 'active', roles: ['assessor'] })).toContain('qualifications')
    expect(keys({ id: 9, status: 'active', roles: ['request_follower'] })).toContain('requestScope')
    expect(keys({ id: 22, status: 'active', roles: ['finance_officer'] })).toEqual([
      'edit',
      'suspend',
    ])
    expect(keys({ id: 1, status: 'active', roles: ['system_admin'] })).toEqual(['edit'])
  })

  it('asks a required reason for suspension and an optional one for reactivation', () => {
    const { api, globals } = withSetup(() => useUserActions({ onChanged: vi.fn() }))
    useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin'], { id: 1 }))
    api.start('suspend', { id: 2, name: 'منيرة' })
    expect(api.dialog.value).toMatchObject({ optional: false, submitLabel: 'Suspend' })
    api.start('activate', { id: 3, name: 'سارة' })
    expect(api.dialog.value.optional).toBe(true)
  })
})

describe('award cycle bindings of a criteria version', () => {
  it('reads the running and draft cycles and sends the draft choice only when drafts exist', async () => {
    criteriaService.checkVersion.mockResolvedValue({
      data: {},
      meta: {
        awardCycles: {
          running: {
            id: 31,
            name: { ar: 'د', en: 'C' },
            status: 'submission-open',
            openRequests: 3,
          },
          requestsStayOnCycleVersion: true,
          drafts: [],
        },
      },
    })
    const { api } = withSetup(() => useCycleBindings())
    await api.load(4)
    expect(api.isRelevant.value).toBe(true)
    expect(api.instruction()).toEqual({})
    criteriaService.checkVersion.mockResolvedValue({
      data: {},
      meta: { awardCycles: { running: null, drafts: [{ id: 32, name: { ar: 'م', en: 'D' } }] } },
    })
    await api.load(4)
    api.choice.value = 'move'
    expect(api.instruction()).toEqual({ draftCycles: 'move' })
    criteriaService.checkVersion.mockRejectedValue(new Error('offline'))
    await api.load(4)
    expect(api.isRelevant.value).toBe(false)
  })
})

describe('award helpers', () => {
  it('builds the secretary payloads and the anonymous entity label', () => {
    expect(decisionPayload('approve', { note: '  ', reason: '' })).toEqual({})
    expect(
      decisionPayload('returnToCommittee', { generalNote: ' ملاحظة إلى اللجنة ', reason: '' }),
    ).toEqual({ generalNote: 'ملاحظة إلى اللجنة' })
    expect(
      decisionPayload('openDiscussion', {
        mode: 'agreed_outcome',
        deadlineDate: '',
        agreedOutcome: 'نتيجة متوافق عليها',
        reason: '',
      }),
    ).toEqual({ mode: 'agreed_outcome', agreedOutcome: 'نتيجة متوافق عليها' })
    expect(committeeEntityLabel({ visibility: 'code_only', code: 'C-1A2B3C4D' }, 'ar')).toBe(
      'C-1A2B3C4D',
    )
    expect(
      committeeEntityLabel(
        { visibility: 'name_only', name: { ar: 'جمعية رواد', en: 'Ruwad' } },
        'en',
      ),
    ).toBe('Ruwad')
  })

  it('sends only the changed award cycle defaults with a two-decimal threshold', () => {
    const settings = {
      generalUploadMaxFileMb: 20,
      advancedMinEntityAgeYears: 3,
      advancedDisqualificationScore: '60.00',
      committeeEntityDataVisibility: 'name_only',
    }
    const values = settingsFormValues(settings)
    expect(changedAssessmentSettings(settings, values)).toEqual({})
    expect(
      changedAssessmentSettings(settings, {
        ...values,
        advancedDisqualificationScore: ' 65 ',
        committeeEntityDataVisibility: 'code_only',
      }),
    ).toEqual({
      advancedDisqualificationScore: '65.00',
      committeeEntityDataVisibility: 'code_only',
    })
  })
})
