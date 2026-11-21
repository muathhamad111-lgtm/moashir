<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import { useToast } from '@/composables/useToast'
import { ACCREDITED_LIMITS } from '@/constants/accredited'
import { normalizedRawValue, scoreFieldName } from '@/utils/assessments/scoreSheet'
import { overrideSummaryParts } from '@/utils/assessments/overrideSummary'
import { localizedText } from '@/utils/localizedText'
import { compactDecimal, twoDecimals } from '@/utils/percentText'
import { logger } from '@/utils/logger'

// The confirmation of a score override (design §B.3.5): only the changed items, before → after,
// the new total and level, and the mandatory reason. Then «اعتماد التجاوز».
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  override: { type: Object, required: true },
  values: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t, locale } = useI18n()
const toast = useToast()
const reason = ref('')
const error = ref('')
const busy = ref(false)

const rows = computed(() =>
  props.override.changedUnits.value.map((unit) => {
    const before = props.override.saved(unit)?.rawValue ?? null
    const after = normalizedRawValue(props.values[scoreFieldName(unit)])
    const delta = Number(after) - Number(before ?? 0)
    return {
      key: scoreFieldName(unit),
      code: unit.code,
      name: localizedText(unit.label, locale.value),
      before: compactDecimal(before),
      after: compactDecimal(after),
      delta: `${delta > 0 ? '+' : ''}${compactDecimal(delta)}`,
    }
  }),
)
const summary = computed(() =>
  overrideSummaryParts(t, {
    count: rows.value.length,
    preview: props.override.preview.value,
    formatPercent: (value) => t('review.override.mode.percent', { value: twoDecimals(value) }),
  }).join(' · '),
)

function validReason() {
  const length = reason.value.trim().length
  const valid =
    length >= ACCREDITED_LIMITS.OVERRIDE_REASON_MIN && length <= ACCREDITED_LIMITS.REASON_MAX
  error.value = valid
    ? ''
    : t('validation.minChars', { min: ACCREDITED_LIMITS.OVERRIDE_REASON_MIN })
  return valid
}

function showFailure(failure) {
  const normalized = failure?.normalized ?? {}
  error.value = normalized.fieldErrors?.reason?.[0] ?? ''
  toast.error(normalized.message || t('errors.unexpected'))
}

async function onSubmit() {
  if (busy.value || !validReason()) return
  busy.value = true
  logger.breadcrumb('form', 'overrides.scores.submit', { fields: ['scores', 'reason'] })
  try {
    await props.override.submit(reason.value.trim())
    toast.success(t('review.override.done'))
    emit('update:modelValue', false)
    emit('done')
  } catch (failure) {
    showFailure(failure)
  } finally {
    busy.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    reason.value = ''
    error.value = ''
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :title="t('review.override.confirmTitle')"
    :subtitle="t('review.override.warning')"
    :busy="busy"
    submit-variant="danger"
    :submit-label="t('review.override.submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="onSubmit"
  >
    <div class="override-review">
      <p class="override-review__summary">{{ summary }}</p>
      <table class="override-review__table">
        <caption class="override-review__caption">
          {{
            t('review.override.changesCaption')
          }}
        </caption>
        <thead>
          <tr>
            <th scope="col">{{ t('review.override.item') }}</th>
            <th scope="col">{{ t('review.override.published') }}</th>
            <th scope="col">{{ t('review.override.newValue') }}</th>
            <th scope="col">{{ t('review.override.delta') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.key">
            <th scope="row">
              <bdi class="criteria-code">{{ row.code }}</bdi>
              {{ row.name }}
            </th>
            <td>
              <bdi dir="ltr">{{ row.before || '—' }}</bdi>
            </td>
            <td>
              <bdi dir="ltr">{{ row.after }}</bdi>
            </td>
            <td>
              <bdi dir="ltr">{{ row.delta }}</bdi>
            </td>
          </tr>
        </tbody>
      </table>
      <BaseTextarea
        v-model="reason"
        name="override-reason"
        :label="t('requests.overrides.reason')"
        :hint="t('entities.reason.hint', { min: ACCREDITED_LIMITS.OVERRIDE_REASON_MIN })"
        :error="error"
        required
        @blur="validReason"
      />
    </div>
  </FormModal>
</template>
