<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { number, object, string } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import AppCombobox from '@/components/form/AppCombobox.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useAuthStore } from '@/stores/auth'
import { committeeService } from '@/services/committeeService'
import { COMMITTEE_LIMITS } from '@/constants/committee'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  cycleId: { type: Number, required: true },
  member: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t } = useI18n()
const auth = useAuthStore()
const candidates = ref([])
const loading = ref(false)
const isSubstitute = Boolean(props.member)
const field = isSubstitute ? 'substituteUserId' : 'userId'
const options = computed(() =>
  candidates.value.map((person) => ({ value: person.id, label: person.name })),
)

const schema = computed(() =>
  object({
    [field]: number().nullable().required(t('validation.required')),
    reason: auth.isSuperAdmin
      ? string()
          .trim()
          .required(t('validation.required'))
          .min(
            COMMITTEE_LIMITS.REASON_MIN,
            t('validation.minChars', { min: COMMITTEE_LIMITS.REASON_MIN }),
          )
      : string()
          .trim()
          .test(
            'min',
            t('validation.minChars', { min: COMMITTEE_LIMITS.REASON_MIN }),
            (value) => !value || value.length >= COMMITTEE_LIMITS.REASON_MIN,
          ),
  }),
)

const form = useValidatedForm({
  schema,
  initialValues: { [field]: null, reason: '' },
  action: 'committee.roster.candidate',
  submit: async (values) => {
    const reason = values.reason.trim()
    const payload = { [field]: values[field], ...(reason ? { reason } : {}) }
    const { data } = isSubstitute
      ? await committeeService.substitute(props.cycleId, props.member.id, payload)
      : await committeeService.appoint(props.cycleId, payload)
    emit('update:modelValue', false)
    emit('done', data)
  },
})

const [candidate] = form.defineField(field)

async function search(term = '') {
  loading.value = true
  try {
    candidates.value = (
      await committeeService.listCandidates(props.cycleId, term ? { search: term } : {})
    ).data
  } catch {
    candidates.value = []
  } finally {
    loading.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    form.resetForm({ values: { [field]: null, reason: '' } })
    search()
  },
)

onMounted(() => {
  if (props.modelValue) search()
})
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="
      isSubstitute
        ? t('award.roster.substitute.title', { name: member.user.name })
        : t('award.roster.appoint.title')
    "
    :busy="form.submitting.value"
    :submit-label="
      isSubstitute ? t('award.roster.actions.substitute') : t('award.roster.actions.appoint')
    "
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="committee-candidate">
      <AppCombobox
        v-model="candidate"
        :options="options"
        :loading="loading"
        remote
        :name="field"
        :label="t('award.roster.candidate')"
        :placeholder="t('award.roster.candidatePlaceholder')"
        :error="form.errors.value[field]"
        required
        @search="search"
      />
      <p class="committee-candidate__hint">{{ t('award.roster.sodHint') }}</p>
      <FieldTextarea
        name="reason"
        :label="t('award.common.reason')"
        :hint="
          auth.isSuperAdmin
            ? t('award.common.reasonRequired', { min: COMMITTEE_LIMITS.REASON_MIN })
            : t('award.common.reasonOptional')
        "
        :rows="3"
        :required="auth.isSuperAdmin"
      />
    </div>
  </FormModal>
</template>
