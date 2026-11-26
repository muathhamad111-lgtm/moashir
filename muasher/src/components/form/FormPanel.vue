<script setup>
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  busy: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  submitLabel: { type: String, default: '' },
  submitVariant: { type: String, default: 'primary' },
})

const emit = defineEmits(['update:modelValue', 'submit'])
const { t } = useI18n()
const titleId = useId()
</script>

<template>
  <section class="form-panel" :aria-labelledby="titleId">
    <header class="form-panel__header">
      <h2 :id="titleId" class="form-panel__title">{{ title }}</h2>
      <p v-if="subtitle" class="form-panel__subtitle">{{ subtitle }}</p>
    </header>
    <form :aria-busy="busy || undefined" novalidate @submit.prevent="emit('submit')">
      <fieldset class="form-panel__fields" :disabled="readonly || busy">
        <slot />
      </fieldset>
      <footer v-if="!readonly" class="form-panel__footer">
        <BaseButton type="submit" :variant="submitVariant" :loading="busy">
          {{ submitLabel || t('actions.save') }}
        </BaseButton>
        <BaseButton variant="secondary" :disabled="busy" @click="emit('update:modelValue', false)">
          {{ t('components.formPanel.undo') }}
        </BaseButton>
      </footer>
    </form>
  </section>
</template>
