<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { PAYMENT_STATUSES } from '@/constants/payments'

const props = defineProps({
  status: { type: String, required: true },
})

const { t } = useI18n()
const STEPS = ['submit', 'send', 'review', 'receipt']
const CURRENT = { [PAYMENT_STATUSES.UNDER_REVIEW]: 2, [PAYMENT_STATUSES.PAID]: 4 }

const current = computed(() => CURRENT[props.status] ?? 2)
const steps = computed(() =>
  STEPS.map((key, index) => ({
    key,
    number: index + 1,
    done: index < current.value,
    active: index === current.value,
    label: t(`portal.transfer.progress.${key}`),
  })),
)
</script>

<template>
  <ol class="transfer-progress">
    <li
      v-for="step in steps"
      :key="step.key"
      class="transfer-progress__step"
      :class="{ 'is-done': step.done, 'is-active': step.active }"
      :aria-current="step.active ? 'step' : undefined"
    >
      <span class="transfer-progress__marker" aria-hidden="true">
        <AppIcon v-if="step.done" name="check" />
        <template v-else>{{ step.number }}</template>
      </span>
      <span class="transfer-progress__label">{{ step.label }}</span>
    </li>
  </ol>
</template>
