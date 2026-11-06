<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useField } from 'vee-validate'
import { KPI_COLOR_TOKENS } from '@/constants/kpis'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
})

const { t } = useI18n()
const { value } = useField(toRef(props, 'name'))
</script>

<template>
  <div class="kpi-form__field">
    <span class="kpi-form__label">{{ label }}</span>
    <div class="kpi-form__swatches" role="radiogroup" :aria-label="label">
      <button
        v-for="token in KPI_COLOR_TOKENS"
        :key="token"
        type="button"
        class="kpi-form__swatch"
        :class="[`kpi-form__swatch--${token}`, { 'is-selected': value === token }]"
        role="radio"
        :aria-checked="value === token"
        @click="value = token"
      >
        <span class="kpi-form__swatch-dot" aria-hidden="true"></span>
        <span class="kpi-form__swatch-label">{{ t(`kpis.admin.colors.${token}`) }}</span>
        <AppIcon v-if="value === token" name="check" class="kpi-form__swatch-check" />
      </button>
    </div>
  </div>
</template>
