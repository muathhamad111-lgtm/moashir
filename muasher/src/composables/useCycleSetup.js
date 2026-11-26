import { computed, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUnsavedGuard } from '@/composables/useUnsavedGuard'
import { SETUP_STEPS, attentionStep, lacksInitialAssessor } from '@/utils/award/cycleSetup'

// An existing cycle may be opened at a given step (`?step=`); otherwise it opens where it needs attention.
function startingStep(cycle, requested) {
  const index = SETUP_STEPS.indexOf(requested)
  return cycle && index >= 0 ? index : SETUP_STEPS.indexOf(attentionStep(cycle))
}

function useSetupSteps(cycle, requested) {
  const { t } = useI18n()
  const at = ref(startingStep(cycle.value, requested))
  const reached = ref(at.value)
  const dirty = ref(false)
  const guard = useUnsavedGuard(dirty, {
    acrossRoutes: true,
    onDiscard: () => (dirty.value = false),
  })
  const stepKey = computed(() => SETUP_STEPS[at.value])
  const stepItems = computed(() =>
    SETUP_STEPS.map((key, index) => ({
      id: key,
      label: t(`cycles.setup.steps.${key}`),
      disabled: !cycle.value && index > reached.value,
      valid: key === 'team' && lacksInitialAssessor(cycle.value) ? false : undefined,
    })),
  )

  function moveTo(index) {
    at.value = Math.min(Math.max(index, 0), SETUP_STEPS.length - 1)
    reached.value = Math.max(reached.value, at.value)
    dirty.value = false
  }

  async function goTo(index) {
    if (index === at.value || stepItems.value[index]?.disabled) return
    if (await guard.allowLeaving()) moveTo(index)
  }

  return { at, stepKey, stepItems, dirty, moveTo, goTo }
}

export function useCycleSetup(cycle, requestedStep) {
  const steps = useSetupSteps(cycle, requestedStep)
  const pending = shallowRef({})
  const identityErrors = shallowRef({})

  function next() {
    steps.moveTo(steps.at.value + 1)
  }

  function stash(values) {
    pending.value = { ...pending.value, ...values }
    identityErrors.value = {}
    next()
  }

  function reject(errors) {
    identityErrors.value = errors
    steps.moveTo(0)
  }

  function created() {
    pending.value = {}
    next()
  }

  return {
    ...steps,
    pending,
    identityErrors,
    next,
    stash,
    reject,
    created,
    previous: () => steps.goTo(steps.at.value - 1),
    goToStep: (key) => steps.goTo(SETUP_STEPS.indexOf(key)),
  }
}
