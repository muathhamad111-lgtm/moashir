<script setup>
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  modelValue: { type: Boolean, required: true },
  number: { type: String, required: true },
  itemsCount: { type: Number, required: true },
  dirty: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="md"
    :title="t('assessment.self.confirm.submitTitle')"
    :close-on-backdrop="!submitting"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="self-submit">
      <p class="self-submit__lead">
        {{ t('assessment.self.confirm.submitMessage', { number }) }}
      </p>
      <ul class="self-submit__facts">
        <li class="self-submit__fact">
          <AppIcon name="list-check" />
          {{ t('assessment.self.confirm.allScored', { count: itemsCount }, itemsCount) }}
        </li>
        <li v-if="dirty" class="self-submit__fact">
          <AppIcon name="save" />
          {{ t('assessment.self.confirm.unsavedIncluded') }}
        </li>
        <li class="self-submit__fact">
          <AppIcon name="calculator" />
          {{ t('assessment.self.confirm.immediateResult') }}
        </li>
        <li class="self-submit__fact">
          <AppIcon name="lock" />
          {{ t('assessment.self.confirm.finalNote') }}
        </li>
      </ul>
    </div>
    <template #footer>
      <BaseButton
        variant="secondary"
        :disabled="submitting"
        @click="emit('update:modelValue', false)"
      >
        {{ t('actions.cancel') }}
      </BaseButton>
      <BaseButton variant="primary" icon="send" :loading="submitting" @click="emit('confirm')">
        {{ t('assessment.self.confirm.confirm') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
