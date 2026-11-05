<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  message: { type: String, required: true },
  offersCertificate: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()
const issueCertificate = ref(true)

watch(
  () => props.modelValue,
  (open) => {
    if (open) issueCertificate.value = true
  },
)

function close() {
  emit('update:modelValue', false)
}

function confirm() {
  emit('confirm', props.offersCertificate ? { issueCertificate: issueCertificate.value } : {})
  close()
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="sm"
    :title="t('review.confirmComplete.title')"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="complete-review">
      <p class="complete-review__message">{{ message }}</p>
      <template v-if="offersCertificate">
        <BaseCheckbox
          v-model="issueCertificate"
          name="issueCertificate"
          :label="t('review.confirmComplete.issueCertificate')"
        />
        <p class="complete-review__hint">
          {{
            issueCertificate
              ? t('review.confirmComplete.issueHint')
              : t('review.confirmComplete.laterHint')
          }}
        </p>
      </template>
    </div>
    <template #footer>
      <BaseButton variant="secondary" @click="close">{{ t('actions.cancel') }}</BaseButton>
      <BaseButton variant="primary" icon="flag-checkered" @click="confirm">{{
        t('review.complete')
      }}</BaseButton>
    </template>
  </BaseModal>
</template>
