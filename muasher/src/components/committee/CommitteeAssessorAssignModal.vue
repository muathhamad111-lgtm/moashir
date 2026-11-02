<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SearchSelect from '@/components/form/SearchSelect.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  options: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  title: { type: String, default: '' },
  lead: { type: String, default: '' },
  fieldLabel: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  confirmLabel: { type: String, default: '' },
  errorMessage: { type: String, default: '' },
  emptyMessage: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()

const selected = ref(null)
const showError = ref(false)

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      selected.value = null
      showError.value = false
    }
  },
  { immediate: true },
)

const hasOptions = computed(() => props.options.length > 0)

function onConfirm() {
  if (!selected.value) {
    showError.value = true
    return
  }
  emit('confirm', selected.value)
}
</script>

<template>
  <BaseModal v-model="isOpen" size="sm" :title="title">
    <div class="committee-assessor-assign">
      <p class="committee-assessor-assign__lead">{{ lead }}</p>
      <div v-if="hasOptions" class="committee-assessor-assign__field">
        <SearchSelect
          :model-value="selected"
          :options="options"
          :loading="loading"
          :label="fieldLabel"
          :placeholder="placeholder"
          :disabled="busy"
          @update:model-value="selected = $event; showError = false"
        />
        <p v-if="showError" class="committee-assessor-assign__error" role="alert">
          <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
          {{ errorMessage }}
        </p>
      </div>
      <p v-else class="committee-assessor-assign__empty" role="note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        {{ emptyMessage }}
      </p>
    </div>
    <template #footer>
      <BaseButton variant="secondary" :disabled="busy" @click="isOpen = false">
        {{ t('actions.cancel') }}
      </BaseButton>
      <BaseButton
        variant="primary"
        :loading="busy"
        :disabled="!hasOptions"
        @click="onConfirm"
      >
        {{ confirmLabel }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
