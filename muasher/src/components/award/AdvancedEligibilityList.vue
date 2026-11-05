<script setup>
import { useI18n } from 'vue-i18n'
import { ELIGIBILITY_RULES } from '@/constants/advanced'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  conditions: { type: Array, required: true },
  windowOpen: { type: Boolean, default: null },
})

const { t } = useI18n()

function textOf(check) {
  if (check.rule === ELIGIBILITY_RULES.MIN_ENTITY_AGE)
    return t('advanced.eligibility.age', { required: check.required, actual: check.actual })
  return t(`advanced.eligibility.rules.${check.rule}`)
}
</script>

<template>
  <ul class="eligibility-list">
    <li
      v-for="check in conditions"
      :key="check.rule"
      class="eligibility-list__item"
      :class="{ 'eligibility-list__item--failed': !check.passed }"
    >
      <AppIcon :name="check.passed ? 'check-circle' : 'x'" />
      <span class="eligibility-list__text">{{ textOf(check) }}</span>
      <span class="eligibility-list__state">{{
        check.passed ? t('advanced.eligibility.passed') : t('advanced.eligibility.failed')
      }}</span>
    </li>
    <li
      v-if="windowOpen !== null"
      class="eligibility-list__item"
      :class="{ 'eligibility-list__item--failed': !windowOpen }"
    >
      <AppIcon :name="windowOpen ? 'check-circle' : 'x'" />
      <span class="eligibility-list__text">{{ t('advanced.eligibility.window') }}</span>
      <span class="eligibility-list__state">{{
        windowOpen ? t('advanced.eligibility.open') : t('advanced.eligibility.closed')
      }}</span>
    </li>
  </ul>
</template>
