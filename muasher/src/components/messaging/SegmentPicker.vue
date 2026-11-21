<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { useSettings } from '@/composables/useSettings'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ segment: '', recipientEntityId: null }),
  },
  segments: { type: Array, default: () => [] },
  recipients: { type: Array, default: () => [] },
  recipientsLoading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'search-recipients'])
const { t } = useI18n()
const { localized } = useSettings()

const isIndividual = computed(() => props.modelValue?.segment === 'individual')

const recipientOptions = computed(() =>
  props.recipients.map((entity) => ({ value: entity.id, label: localized(entity.name) })),
)

function selectSegment(segment) {
  if (props.disabled) return
  emit('update:modelValue', {
    segment,
    recipientEntityId:
      segment === 'individual' ? (props.modelValue?.recipientEntityId ?? null) : null,
  })
}

function selectRecipient(recipientEntityId) {
  emit('update:modelValue', { ...props.modelValue, recipientEntityId })
}

function segmentLabel(segment) {
  return localized(segment.label) || t(`settings.email.segments.${segment.key}`)
}
</script>

<template>
  <div class="segment-picker">
    <fieldset class="segment-picker__group" :disabled="disabled">
      <legend class="segment-picker__legend">{{ t('settings.email.audienceLabel') }}</legend>
      <ul class="segment-picker__list">
        <li v-for="segment in segments" :key="segment.key" class="segment-picker__item">
          <label
            class="segment-picker__option"
            :class="{ 'is-selected': modelValue.segment === segment.key }"
          >
            <input
              class="segment-picker__radio"
              type="radio"
              name="segment"
              :value="segment.key"
              :checked="modelValue.segment === segment.key"
              :disabled="disabled"
              @change="selectSegment(segment.key)"
            />
            <span class="segment-picker__option-body">
              <span class="segment-picker__option-title">{{ segmentLabel(segment) }}</span>
              <span v-if="segment.key !== 'individual'" class="segment-picker__count">
                {{ t('settings.email.recipientCount', { count: segment.count }) }}
              </span>
            </span>
          </label>
        </li>
      </ul>
      <p v-if="error" class="field__error" role="alert">{{ error }}</p>
    </fieldset>

    <div v-if="isIndividual" class="segment-picker__individual">
      <AppCombobox
        :model-value="modelValue.recipientEntityId"
        name="recipientEntityId"
        :options="recipientOptions"
        remote
        :loading="recipientsLoading"
        :label="t('settings.email.pickRecipient')"
        :placeholder="t('settings.email.pickRecipientPlaceholder')"
        :disabled="disabled"
        @update:model-value="selectRecipient"
        @search="emit('search-recipients', $event)"
      />
    </div>
  </div>
</template>
