<script setup>
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  palette: { type: Array, required: true },
  modelValue: { type: String, required: true },
  localized: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const groupName = useId()
</script>

<template>
  <fieldset class="accent-swatches" :disabled="disabled">
    <legend class="accent-swatches__legend">{{ t('branding.accent.legend') }}</legend>
    <div class="accent-swatches__grid">
      <label
        v-for="entry in palette"
        :key="entry.token"
        class="accent-swatch"
        :class="{ 'is-selected': entry.token === modelValue }"
      >
        <input
          class="accent-swatch__input"
          type="radio"
          :name="groupName"
          :value="entry.token"
          :checked="entry.token === modelValue"
          @change="emit('update:modelValue', entry.token)"
        />
        <span class="accent-swatch__chip" :data-accent="entry.token" aria-hidden="true">
          <AppIcon v-if="entry.token === modelValue" name="check" />
        </span>
        <span class="accent-swatch__label">{{ localized(entry.label) }}</span>
      </label>
    </div>
  </fieldset>
</template>
