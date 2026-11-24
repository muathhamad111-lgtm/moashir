import { useI18n } from 'vue-i18n'
import { awardCycleService } from '@/services/awardCycleService'
import { useToast } from '@/composables/useToast'
import { AWARD_CODES } from '@/constants/advanced'
import { changedCyclePayload, cyclePayload } from '@/utils/award/cycleForm'
import { CREATING_STEP, SETUP_STEP_FIELDS, stepValues } from '@/utils/award/cycleSetup'

const IDENTITY_FIELDS = SETUP_STEP_FIELDS.identity

function identityErrorsOf(error) {
  const fieldErrors = error?.normalized?.fieldErrors ?? {}
  return Object.fromEntries(
    Object.entries(fieldErrors).filter(([field]) =>
      IDENTITY_FIELDS.some((name) => field === name || field.startsWith(`${name}.`)),
    ),
  )
}

export function useCycleStepSave({ props, emit }) {
  const { t } = useI18n()
  const toast = useToast()

  async function send(request) {
    const extra = await props.reasonExtra()
    if (extra === null) return null
    return (await request(extra)).data
  }

  async function create(values) {
    if (props.step !== CREATING_STEP) return emit('stash', values)
    const payload = cyclePayload(
      { ...stepValues(props.values, 'identity'), ...values },
      { includeParticipation: false },
    )
    try {
      const detail = await send((extra) => awardCycleService.create({ ...payload, ...extra }))
      if (!detail) return null
      toast.success(t('cycles.form.created'))
      return emit('created', detail)
    } catch (error) {
      const identityErrors = identityErrorsOf(error)
      if (Object.keys(identityErrors).length) emit('rejected', identityErrors)
      throw error
    }
  }

  async function update(values) {
    const changes = changedCyclePayload(values, props.values, {
      includeParticipation: props.canSetParticipation,
      locked: props.locked,
    })
    if (!Object.keys(changes).length) return emit('next', null)
    try {
      const detail = await send((extra) =>
        awardCycleService.update(props.cycle.id, { ...changes, ...extra }),
      )
      if (!detail) return null
      toast.success(t('cycles.form.updated'))
      return emit('next', detail)
    } catch (error) {
      if (error?.normalized?.code === AWARD_CODES.FIELD_LOCKED)
        emit('locked', error.normalized.meta?.lockedFields ?? [])
      throw error
    }
  }

  return (values) => (props.cycle ? update(values) : create(values))
}
