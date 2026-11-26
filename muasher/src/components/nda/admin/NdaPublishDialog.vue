<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { ndaService } from '@/services/ndaService'
import { ndaPublishPayload, ndaPublishSchema } from '@/utils/nda/ndaForms'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  draft: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'published'])

const { t } = useI18n()
const impact = ref(null)

const form = useValidatedForm({
  schema: computed(() => ndaPublishSchema(t)),
  initialValues: { changeSummary: { ar: '', en: '' }, reason: '', confirmIrreversible: false },
  action: 'nda.admin.publish',
  submit: async (values) => {
    const { data } = await ndaService.publish(ndaPublishPayload(values))
    emit('update:modelValue', false)
    emit('published', { version: data, reached: impact.value?.total ?? 0 })
  },
})

const roleCounts = computed(() =>
  (impact.value?.roles ?? [])
    .filter((item) => item.count > 0)
    .map((item) =>
      t('nda.admin.publish.roleCount', { role: roleLabel(item.role), count: item.count }),
    )
    .join(' · '),
)

function roleLabel(role) {
  return role === 'super_admin' ? t('nda.roles.superAdmin') : t(`users.roleLabels.${role}`)
}

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    form.resetForm()
    impact.value = null
    impact.value = (await ndaService.impact()).data
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('nda.admin.publish.title', { version: draft?.version ?? '' })"
    :busy="form.submitting.value"
    :submit-label="t('actions.publish')"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="nda-admin" data-testid="nda-publish">
      <FieldTextarea
        name="changeSummary.ar"
        :label="t('nda.admin.publish.summaryAr')"
        :rows="3"
        required
      />
      <FieldTextarea
        name="changeSummary.en"
        :label="t('nda.admin.publish.summaryEn')"
        :rows="3"
        required
      />
      <p v-if="impact" class="nda-publish__impact" aria-live="polite">
        <strong>{{ t('nda.admin.publish.reach', { count: impact.total }) }}</strong>
        <br v-if="roleCounts" />
        <span v-if="roleCounts">{{ roleCounts }}</span>
      </p>
      <FieldTextarea
        name="reason"
        :label="t('nda.admin.publish.reason')"
        :hint="t('nda.admin.publish.reasonHint')"
        :rows="2"
        required
      />
      <FieldCheckbox name="confirmIrreversible" :label="t('nda.admin.publish.confirm')" />
    </div>
  </FormModal>
</template>
