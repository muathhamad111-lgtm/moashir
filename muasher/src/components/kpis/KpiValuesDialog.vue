<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import KpiHistoryTable from './KpiHistoryTable.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useToast } from '@/composables/useToast'
import { kpisAdminService } from '@/services/kpisAdminService'
import { measurementSchema } from '@/utils/kpiSchemas'
import { riyadhDateKey } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  kpi: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'recorded'])
const { t, locale } = useI18n()
const toast = useToast()
const history = ref([])
const status = ref('loading')
const today = riyadhDateKey(new Date())

const unit = computed(() => localizedText(props.kpi?.unit, locale.value))

async function load() {
  status.value = 'loading'
  try {
    const { data } = await kpisAdminService.listValues(props.kpi.id)
    history.value = data ?? []
    status.value = 'success'
  } catch {
    status.value = 'error'
  }
}

const form = useValidatedForm({
  schema: computed(() => measurementSchema(t, today)),
  initialValues: { value: '', measuredOn: today },
  action: 'kpis.values.record',
  submit: async (values) => {
    const { data } = await kpisAdminService.recordValue(props.kpi.id, {
      value: String(values.value).trim(),
      measuredOn: values.measuredOn,
    })
    history.value = data ?? []
    form.resetForm({ values: { value: '', measuredOn: today } })
    toast.success(t('kpis.admin.values.recorded'))
    emit('recorded')
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (!open || !props.kpi) return
    form.resetForm({ values: { value: '', measuredOn: today } })
    load()
  },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="md"
    :title="kpi ? t('kpis.admin.values.title', { name: localizedText(kpi.name, locale) }) : ''"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="kpi-values">
      <form class="kpi-values__form" novalidate @submit.prevent="form.onSubmit">
        <FieldInput
          name="value"
          inputmode="decimal"
          :label="t('kpis.history.value')"
          input-dir="ltr"
          required
        />
        <FieldInput
          name="measuredOn"
          type="date"
          :label="t('kpis.history.date')"
          :hint="t('kpis.admin.values.dateHint')"
          required
        />
        <BaseButton
          type="submit"
          variant="primary"
          size="sm"
          icon="plus"
          :loading="form.submitting.value"
        >
          {{ t('kpis.admin.values.record') }}
        </BaseButton>
      </form>
      <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
      <ErrorState
        v-else-if="status === 'error'"
        :message="t('kpis.admin.values.loadError')"
        @retry="load"
      />
      <KpiHistoryTable
        v-else-if="history.length"
        :history="history"
        :unit="unit"
        :caption="t('kpis.detail.history')"
      />
      <p v-else class="kpi-values__empty">{{ t('kpis.admin.values.empty') }}</p>
    </div>
  </BaseModal>
</template>
