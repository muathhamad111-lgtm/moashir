<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import CycleBasicsFields from './CycleBasicsFields.vue'
import CycleWindowFields from './CycleWindowFields.vue'
import CycleConditionFields from './CycleConditionFields.vue'
import CycleFeeFields from './CycleFeeFields.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { awardCycleService } from '@/services/awardCycleService'
import { useToast } from '@/composables/useToast'
import { AWARD_CODES } from '@/constants/advanced'
import { changedCyclePayload, cyclePayload, cycleSchema } from '@/utils/award/cycleForm'

const props = defineProps({
  cycleId: { type: Number, default: null },
  initialValues: { type: Object, required: true },
  locked: { type: Object, required: true },
  regions: { type: Array, required: true },
  published: { type: Object, default: null },
  canSetParticipation: { type: Boolean, default: false },
  editable: { type: Boolean, default: true },
  reasonExtra: { type: Function, required: true },
  feeQuote: { type: Object, default: null },
})

const emit = defineEmits(['created', 'updated', 'locked'])
const { t } = useI18n()
const toast = useToast()

const allLocked = computed(() =>
  props.editable ? props.locked : new Set(Object.keys(props.initialValues)),
)

async function send(values, extra) {
  const includeParticipation = props.canSetParticipation
  if (!props.cycleId)
    return {
      created: true,
      data: (
        await awardCycleService.create({
          ...cyclePayload(values, { includeParticipation }),
          ...extra,
        })
      ).data,
    }
  const changes = changedCyclePayload(values, props.initialValues, {
    includeParticipation,
    locked: props.locked,
  })
  if (!Object.keys(changes).length) return { created: false, data: null }
  return {
    created: false,
    data: (await awardCycleService.update(props.cycleId, { ...changes, ...extra })).data,
  }
}

const cycleForm = useValidatedForm({
  schema: computed(() => cycleSchema(t)),
  initialValues: props.initialValues,
  action: 'cycles.save',
  submit: async (values) => {
    const extra = await props.reasonExtra()
    if (extra === null) return
    try {
      const { created, data } = await send(values, extra)
      if (!data) {
        toast.info(t('cycles.form.noChanges'))
        return
      }
      toast.success(t(created ? 'cycles.form.created' : 'cycles.form.updated'))
      emit(created ? 'created' : 'updated', data)
    } catch (error) {
      if (error?.normalized?.code === AWARD_CODES.FIELD_LOCKED)
        emit('locked', error.normalized.meta?.lockedFields ?? [])
      throw error
    }
  },
})

watch(
  () => props.initialValues,
  (values) => cycleForm.resetForm({ values }),
)

const versionId = computed(() =>
  cycleForm.values.criteriaVersionId === null || cycleForm.values.criteriaVersionId === undefined
    ? null
    : Number(cycleForm.values.criteriaVersionId),
)
</script>

<template>
  <form class="cycle-form" novalidate @submit.prevent="cycleForm.onSubmit">
    <CycleBasicsFields :locked="allLocked" />
    <CycleWindowFields :locked="allLocked" />
    <CycleConditionFields
      :locked="allLocked"
      :regions="regions"
      :published="published"
      :version-id="versionId"
      :can-set-participation="canSetParticipation && editable"
    />
    <CycleFeeFields :locked="allLocked" :quote="feeQuote" :is-new="!cycleId" />
    <div v-if="editable" class="cycle-form__actions">
      <BaseButton type="submit" variant="primary" icon="save" :loading="cycleForm.submitting.value">
        {{ cycleId ? t('cycles.form.save') : t('cycles.form.create') }}
      </BaseButton>
    </div>
  </form>
</template>
