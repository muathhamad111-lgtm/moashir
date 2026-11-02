<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import { toHundredths, weightBudget } from '@/utils/weight'

const props = defineProps({
  capacity: { type: String, required: true },
  allocated: { type: String, required: true },
  capacityLabel: { type: String, required: true },
  exhaustedMessage: { type: String, default: '' },
  equalDistribution: { type: Boolean, default: false },
})

const { t } = useI18n()
const { weight } = useCriteria()

const budget = computed(() => weightBudget(props.capacity, props.allocated))
const capacityHundredths = computed(() => Math.max(1, toHundredths(props.capacity)))
const filled = computed(() => Math.min(toHundredths(props.allocated), capacityHundredths.value))

const ICONS = {
  balanced: 'fa-solid fa-circle-check',
  under: 'fa-solid fa-scale-unbalanced',
  over: 'fa-solid fa-circle-exclamation',
}

const message = computed(() => {
  if (budget.value.state === 'over') {
    return t('criteria.budget.over', { over: weight(budget.value.remaining.replace('-', '')) })
  }
  if (budget.value.state === 'under')
    return t('criteria.budget.under', { remaining: weight(budget.value.remaining) })
  return props.exhaustedMessage || t('criteria.budget.balanced')
})
</script>

<template>
  <section class="weight-budget" :class="`weight-budget--${budget.state}`">
    <dl class="weight-budget__figures">
      <div class="weight-budget__figure">
        <dt>{{ capacityLabel }}</dt>
        <dd>{{ weight(budget.capacity) }}</dd>
      </div>
      <div class="weight-budget__figure">
        <dt>{{ t('criteria.budget.allocated') }}</dt>
        <dd>{{ weight(budget.allocated) }}</dd>
      </div>
      <div class="weight-budget__figure">
        <dt>{{ t('criteria.budget.remaining') }}</dt>
        <dd class="weight-budget__remaining">{{ weight(budget.remaining) }}</dd>
      </div>
    </dl>
    <progress
      class="weight-budget__bar"
      :max="capacityHundredths"
      :value="filled"
      :aria-label="t('criteria.budget.progressLabel')"
    ></progress>
    <p class="weight-budget__status" role="status">
      <i :class="ICONS[budget.state]" aria-hidden="true"></i>
      <span>{{ message }}</span>
    </p>
    <p v-if="equalDistribution" class="weight-budget__note">
      <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
      {{ t('criteria.budget.equalNote') }}
    </p>
  </section>
</template>
