<script setup>
import { computed, useId } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  regions: { type: Array, required: true },
  lockedRegionIds: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const baseId = useId()
const hintId = `${baseId}-hint`
const { value, errorMessage, handleBlur } = useField('regionIds')
const options = computed(() =>
  props.regions.map((region) => {
    const locked = props.lockedRegionIds.includes(region.id)
    const hint = locked ? t('cycles.setup.regions.locked') : undefined
    return {
      value: region.id,
      label: localizedText(region.name, locale.value),
      disabled: locked,
      hint,
      description: hint,
    }
  }),
)
const countLabel = computed(() =>
  t('cycles.setup.regions.count', {
    count: (value.value ?? []).length,
    total: options.value.length,
  }),
)
</script>

<template>
  <div class="cycle-regions">
    <p class="cycle-regions__title">{{ t('cycles.form.regions') }}</p>
    <p :id="hintId" class="cycle-form__hint">{{ t('cycles.form.regionsHint') }}</p>
    <AppCombobox
      v-model="value"
      name="regionIds"
      multiple
      select-all
      required
      :count-label="countLabel"
      :options="options"
      :label="t('cycles.setup.regions.choose')"
      :described-by="hintId"
      :error="errorMessage"
      :disabled="disabled"
      @blur="handleBlur"
    />
  </div>
</template>
