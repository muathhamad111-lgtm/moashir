<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { TEST_MODE_AUDIENCES, TEST_MODE_ICONS } from '@/constants/payments'

const props = defineProps({
  compact: { type: Boolean, default: false },
  audience: {
    type: String,
    default: TEST_MODE_AUDIENCES.ENTITY,
    validator: (value) => Object.values(TEST_MODE_AUDIENCES).includes(value),
  },
})

const { t } = useI18n()
const base = computed(() => `finance.testMode.${props.audience}`)
</script>

<template>
  <p class="test-mode-notice" :class="{ 'test-mode-notice--compact': compact }" role="note">
    <AppIcon :name="TEST_MODE_ICONS[audience]" />
    <span>
      <strong class="test-mode-notice__title">{{ t(`${base}.title`) }}</strong>
      <span v-if="!compact" class="test-mode-notice__body">{{ t(`${base}.body`) }}</span>
    </span>
  </p>
</template>
