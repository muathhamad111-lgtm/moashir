<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import SearchSelect from '@/components/form/SearchSelect.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  mode: {
    type: String,
    default: 'assign',
    validator: (value) => ['assign', 'reassign'].includes(value),
  },
  entityName: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  currentEvaluatorId: { type: [Number, String, null], default: null },
})

const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()

const selected = ref(null)
const reason = ref('')
const showSelectError = ref(false)
const showReasonError = ref(false)

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const isReassign = computed(() => props.mode === 'reassign')

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      selected.value = props.currentEvaluatorId ?? null
      reason.value = ''
      showSelectError.value = false
      showReasonError.value = false
    }
  },
  { immediate: true },
)

const title = computed(() =>
  isReassign.value ? t('bagRequests.assign.reassignTitle') : t('bagRequests.assign.title'),
)
const confirmLabel = computed(() =>
  isReassign.value ? t('bagRequests.assign.reassignAction') : t('bagRequests.assign.action'),
)

function onConfirm() {
  showSelectError.value = !selected.value
  showReasonError.value = isReassign.value && !reason.value.trim()
  if (showSelectError.value || showReasonError.value) return
  emit('confirm', { evaluatorId: selected.value, reason: reason.value.trim() })
}
</script>

<template>
  <BaseModal v-model="isOpen" size="sm" :title="title">
    <div class="assign-modal">
      <p class="assign-modal__lead">{{ t('bagRequests.assign.lead', { entity: entityName }) }}</p>
      <div class="assign-modal__field">
        <SearchSelect
          :model-value="selected"
          :options="options"
          :loading="loading"
          :label="t('bagRequests.assign.evaluatorLabel')"
          :placeholder="t('bagRequests.assign.evaluatorPlaceholder')"
          :disabled="busy"
          @update:model-value="selected = $event; showSelectError = false"
        />
        <p v-if="showSelectError" class="assign-modal__error" role="alert">
          <AppIcon name="warning-triangle" />
          {{ t('bagRequests.assign.chooseError') }}
        </p>
      </div>

      <div v-if="isReassign" class="assign-modal__field">
        <BaseTextarea
          v-model="reason"
          :label="t('bagRequests.assign.reasonLabel')"
          name="bag-reassign-reason"
          :rows="2"
          :placeholder="t('bagRequests.assign.reasonPlaceholder')"
          :disabled="busy"
          @update:model-value="showReasonError = false"
        />
        <p v-if="showReasonError" class="assign-modal__error" role="alert">
          <AppIcon name="warning-triangle" />
          {{ t('bagRequests.assign.reasonError') }}
        </p>
      </div>
    </div>
    <template #footer>
      <BaseButton variant="secondary" :disabled="busy" @click="isOpen = false">
        {{ t('actions.cancel') }}
      </BaseButton>
      <BaseButton variant="primary" :loading="busy" @click="onConfirm">
        {{ confirmLabel }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
