<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import MethodOption from './MethodOption.vue'
import { CHANNEL_ICONS } from '@/constants/bankTransfer'

const props = defineProps({
  channels: { type: Array, required: true },
  modelValue: { type: String, default: null },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'continue'])
const { t } = useI18n()

const options = computed(() =>
  props.channels.map((channel) => ({
    value: channel,
    icon: CHANNEL_ICONS[channel],
    title: t(`portal.checkoutMethod.options.${channel}.title`),
    description: t(`portal.checkoutMethod.options.${channel}.description`),
    meta: [{ text: t(`portal.checkoutMethod.options.${channel}.meta`) }],
  })),
)
</script>

<template>
  <section class="method-choice" aria-labelledby="method-choice-title">
    <h2 id="method-choice-title" class="method-choice__title">
      {{ t('portal.checkoutMethod.title') }}
    </h2>
    <div class="method-choice__options" role="radiogroup" aria-labelledby="method-choice-title">
      <MethodOption
        v-for="option in options"
        :key="option.value"
        :model-value="modelValue"
        name="checkout-channel"
        :value="option.value"
        :icon="option.icon"
        :title="option.title"
        :description="option.description"
        :meta="option.meta"
        :disabled="busy"
        @update:model-value="emit('update:modelValue', $event)"
      />
    </div>
    <div class="checkout__actions">
      <BaseButton
        variant="primary"
        size="lg"
        icon-end="arrow-right"
        :loading="busy"
        :disabled="!modelValue || busy"
        @click="emit('continue', modelValue)"
      >
        {{ t('portal.checkoutMethod.continue') }}
      </BaseButton>
      <slot name="back" />
    </div>
  </section>
</template>
