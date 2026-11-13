<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { CRITERIA_EDIT_SCOPE_ORDER, CRITERIA_EDIT_SCOPES } from '@/constants/criteria'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  modelValue: { type: String, default: null },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const baseId = useId()

const ICONS = {
  [CRITERIA_EDIT_SCOPES.ALL]: 'layers',
  [CRITERIA_EDIT_SCOPES.NEW_ONLY]: 'doc-plus-fill',
  [CRITERIA_EDIT_SCOPES.RESET_OPEN]: 'undo',
}

const options = computed(() =>
  CRITERIA_EDIT_SCOPE_ORDER.map((scope) => ({
    value: scope,
    icon: ICONS[scope],
    title: t(`criteria.publish.scopes.${scope}.title`),
    description: t(`criteria.publish.scopes.${scope}.description`),
  })),
)

const errorId = computed(() => `${baseId}-error`)
</script>

<template>
  <fieldset
    class="scope-choice"
    :aria-describedby="error ? errorId : undefined"
    :disabled="disabled"
  >
    <legend class="scope-choice__legend">
      {{ t('criteria.publish.scopeLegend') }}
      <span class="scope-choice__required" aria-hidden="true">*</span>
    </legend>
    <div class="scope-choice__options">
      <label
        v-for="option in options"
        :key="option.value"
        class="scope-choice__option"
        :class="{ 'is-selected': modelValue === option.value }"
      >
        <input
          class="scope-choice__input"
          type="radio"
          name="criteriaEditScope"
          :value="option.value"
          :checked="modelValue === option.value"
          :aria-describedby="`${baseId}-${option.value}`"
          required
          @change="emit('update:modelValue', option.value)"
        />
        <span class="scope-choice__icon" aria-hidden="true"><AppIcon :name="option.icon" /></span>
        <span class="scope-choice__text">
          <span class="scope-choice__title">{{ option.title }}</span>
          <span :id="`${baseId}-${option.value}`" class="scope-choice__description">{{
            option.description
          }}</span>
        </span>
      </label>
    </div>
    <p class="scope-choice__closed-note">
      <AppIcon name="shield-info" />
      {{ t('criteria.publish.closedNote') }}
    </p>
    <p v-if="error" :id="errorId" class="field__error" role="alert">{{ error }}</p>
  </fieldset>
</template>
