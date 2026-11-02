<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import { percentOf, sumWeights, weightBudget } from '@/utils/weight'

const props = defineProps({
  parentWeight: { type: String, required: true },
  children: { type: Array, default: () => [] },
  mode: {
    type: String,
    default: 'manual',
    validator: (value) => ['equal', 'manual'].includes(value),
  },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:children', 'update:mode'])
const { t } = useI18n()
const { weight } = useCriteria()

const activeWeights = computed(() =>
  props.children.filter((child) => child.active !== false).map((child) => child.weight),
)
const summary = computed(() => weightBudget(props.parentWeight, sumWeights(activeWeights.value)))

const STATUS = {
  balanced: { tone: 'success', icon: 'fa-circle-check' },
  under: { tone: 'warning', icon: 'fa-triangle-exclamation' },
  over: { tone: 'danger', icon: 'fa-circle-exclamation' },
}

const status = computed(() => STATUS[summary.value.state])

const statusMessage = computed(() => {
  if (summary.value.state === 'over') {
    return t('criteria.weights.overAllocated', {
      over: weight(summary.value.remaining.replace('-', '')),
    })
  }
  if (summary.value.state === 'under')
    return t('criteria.weights.underAllocated', { remaining: weight(summary.value.remaining) })
  return t('criteria.weights.balanced')
})

function setMode(mode) {
  if (props.disabled || mode === props.mode) return
  emit('update:mode', mode)
}

function updateWeight(childId, value) {
  emit(
    'update:children',
    props.children.map((child) => (child.id === childId ? { ...child, weight: value } : child)),
  )
}

function isEditable(child) {
  return !props.disabled && props.mode === 'manual' && child.active !== false
}
</script>

<template>
  <section class="weight-editor" :class="{ 'weight-editor--disabled': disabled }">
    <header class="weight-editor__head">
      <div class="weight-editor__modes" role="group" :aria-label="t('criteria.weights.modeLabel')">
        <button
          v-for="option in ['equal', 'manual']"
          :key="option"
          type="button"
          class="weight-editor__mode"
          :class="{ 'is-active': mode === option }"
          :aria-pressed="mode === option"
          :disabled="disabled"
          @click="setMode(option)"
        >
          <i
            class="fa-solid"
            :class="option === 'equal' ? 'fa-scale-balanced' : 'fa-sliders'"
            aria-hidden="true"
          ></i>
          {{ t(`criteria.weights.${option}`) }}
        </button>
      </div>
      <dl class="weight-editor__totals">
        <div class="weight-editor__total">
          <dt>{{ t('criteria.weights.parentWeight') }}</dt>
          <dd>{{ weight(parentWeight) }}</dd>
        </div>
        <div class="weight-editor__total">
          <dt>{{ t('criteria.weights.allocated') }}</dt>
          <dd>{{ weight(summary.allocated) }}</dd>
        </div>
        <div class="weight-editor__total">
          <dt>{{ t('criteria.weights.remaining') }}</dt>
          <dd :class="{ 'is-negative': summary.state === 'over' }">
            {{ weight(summary.remaining) }}
          </dd>
        </div>
      </dl>
    </header>

    <ul v-if="children.length" class="weight-editor__list">
      <li
        v-for="child in children"
        :key="child.id"
        class="weight-editor__row"
        :class="{ 'is-inactive': child.active === false }"
      >
        <span class="weight-editor__name">{{ child.label }}</span>
        <div class="weight-editor__control">
          <input
            class="field__control weight-editor__input"
            type="text"
            inputmode="decimal"
            dir="ltr"
            :value="child.weight"
            :disabled="!isEditable(child)"
            :aria-label="t('criteria.weights.weightForAria', { name: child.label })"
            @input="updateWeight(child.id, $event.target.value)"
          />
          <span v-if="child.active === false" class="weight-editor__inactive">{{
            t('criteria.weights.inactive')
          }}</span>
          <span v-else class="weight-editor__percent">
            {{ t('criteria.weights.percent', { value: percentOf(child.weight, parentWeight) }) }}
          </span>
        </div>
      </li>
    </ul>
    <p v-else class="weight-editor__empty">{{ t('criteria.weights.noChildren') }}</p>

    <p v-if="mode === 'equal'" class="weight-editor__note">
      {{ t('criteria.weights.equalActive') }}
    </p>
    <p class="weight-editor__status" :class="`weight-editor__status--${status.tone}`" role="status">
      <i class="fa-solid" :class="status.icon" aria-hidden="true"></i>
      {{ statusMessage }}
    </p>
  </section>
</template>
