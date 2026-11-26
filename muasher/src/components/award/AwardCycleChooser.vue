<script setup>
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { isoDate } from '@/utils/datetime'

defineProps({
  choices: { type: Array, required: true },
  modelValue: { type: Number, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()
const baseId = useId()
</script>

<template>
  <fieldset
    class="award-cycle-chooser radio-group"
    :disabled="disabled"
    data-testid="award-cycle-chooser"
  >
    <legend class="radio-group__legend">{{ t('advanced.chooser.label') }}</legend>
    <p class="award-cycle-chooser__hint">{{ t('advanced.chooser.hint') }}</p>
    <div class="radio-group__options">
      <label
        v-for="choice in choices"
        :key="choice.id"
        class="radio"
        :for="`${baseId}-${choice.id}`"
      >
        <input
          :id="`${baseId}-${choice.id}`"
          class="radio__input"
          type="radio"
          :name="baseId"
          :value="choice.id"
          :checked="choice.id === modelValue"
          :data-testid="`award-cycle-choice-${choice.id}`"
          @change="emit('update:modelValue', choice.id)"
        />
        <span class="radio__label">
          {{ localizedText(choice.name, locale) }} <bdi class="u-ltr">{{ choice.year }}</bdi>
          <span class="award-cycle-chooser__option-window">
            {{
              t('advanced.chooser.window', {
                start: isoDate(choice.submissionStart),
                end: isoDate(choice.submissionEnd),
              })
            }}
          </span>
        </span>
      </label>
    </div>
  </fieldset>
</template>
