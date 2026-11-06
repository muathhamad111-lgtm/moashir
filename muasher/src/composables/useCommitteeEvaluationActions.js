import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { committeeWorkService } from '@/services/committeeWorkService'
import { useToast } from '@/composables/useToast'
import { changedSheetPayload, isEmptyPayload } from '@/utils/assessments/scoreSheet'
import { COMMITTEE_CODES } from '@/constants/committee'

const CONFLICTS = [
  COMMITTEE_CODES.EVALUATION_LOCKED,
  COMMITTEE_CODES.NOT_STARTED,
  COMMITTEE_CODES.MEMBERSHIP_INACTIVE,
  COMMITTEE_CODES.ENTITY_DELETED,
  COMMITTEE_CODES.TRANSITION_ILLEGAL,
]

export function useCommitteeEvaluationActions({ state, scoreSheet, notes, reasonExtra }) {
  const { t } = useI18n()
  const toast = useToast()
  const busy = ref('')
  const conflictCode = ref('')

  function handle(error) {
    const normalized = error?.normalized ?? {}
    if (CONFLICTS.includes(normalized.code)) conflictCode.value = normalized.code
    scoreSheet.applyServerErrors(normalized.fieldErrors)
    toast.error(normalized.message || t('errors.unexpected'))
  }

  async function sendScores(id, extra) {
    const payload = changedSheetPayload(
      scoreSheet.sheet.values,
      state.units.value,
      state.evaluation.value.scores,
    )
    if (isEmptyPayload(payload)) return
    const { data } = await committeeWorkService.saveScores(id, { scores: payload, ...extra })
    state.patch({
      scores: data.scores,
      progress: data.progress,
      status: data.status,
      percent: data.percent,
    })
    scoreSheet.resetFrom(data.scores)
  }

  async function sendNotes(id, extra) {
    const payload = notes.changedPayload()
    if (!payload) return
    const { data } = await committeeWorkService.saveNotes(id, { ...payload, ...extra })
    notes.resetFrom({ subs: data.subs, general: data.general })
  }

  async function perform(action, run, successKey) {
    if (busy.value) return false
    busy.value = action
    try {
      const extra = await reasonExtra()
      if (extra === null) return false
      const id = state.evaluation.value.assignment.id
      await run(id, extra)
      toast.success(t(successKey))
      return true
    } catch (error) {
      handle(error)
      return false
    } finally {
      busy.value = ''
    }
  }

  async function save() {
    if (!(await scoreSheet.isValid())) {
      toast.error(t('award.evaluation.toasts.fixErrors'))
      return false
    }
    return perform(
      'save',
      async (id, extra) => {
        await sendScores(id, extra)
        await sendNotes(id, extra)
      },
      'award.evaluation.toasts.saved',
    )
  }

  function submit() {
    return perform(
      'submit',
      async (id, extra) => {
        await sendScores(id, extra)
        await sendNotes(id, extra)
        await committeeWorkService.submit(id, extra)
        await state.load({ silent: true })
      },
      'award.evaluation.toasts.submitted',
    )
  }

  function clearConflict() {
    conflictCode.value = ''
  }

  return { busy, conflictCode, save, submit, clearConflict }
}
