<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import { OVERVIEW_PERIODS, OVERVIEW_PRESETS } from '@/constants/dashboardOverview'
import { riyadhDateKey } from '@/utils/datetime'

const props = defineProps({
  period: { type: String, required: true },
  from: { type: String, default: '' },
  to: { type: String, default: '' },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['preset', 'custom'])
const { t } = useI18n()
const today = riyadhDateKey(new Date())
const draftFrom = ref(props.from)
const draftTo = ref(props.to)

watch(
  () => [props.from, props.to],
  ([from, to]) => {
    draftFrom.value = from
    draftTo.value = to
  },
)

function errorOf(key) {
  return props.errors?.[key]?.[0] ?? ''
}

function apply() {
  if (draftFrom.value && draftTo.value) emit('custom', draftFrom.value, draftTo.value)
}
</script>

<template>
  <div class="overview-period">
    <div
      class="overview-period__presets"
      role="group"
      :aria-label="t('dashboard.overview.period.label')"
    >
      <button
        v-for="preset in OVERVIEW_PRESETS"
        :key="preset"
        type="button"
        class="overview-period__preset"
        :class="{ 'is-active': period === preset }"
        :aria-pressed="period === preset ? 'true' : 'false'"
        :data-period="preset"
        @click="emit('preset', preset)"
      >
        {{ t(`dashboard.overview.period.presets.${preset}`) }}
      </button>
    </div>
    <form
      v-if="period === OVERVIEW_PERIODS.CUSTOM"
      class="overview-period__custom"
      novalidate
      @submit.prevent="apply"
    >
      <BaseDatePicker
        v-model="draftFrom"
        name="overview-from"
        :label="t('dashboard.overview.period.from')"
        :max="draftTo || today"
        :error="errorOf('from')"
        required
      />
      <BaseDatePicker
        v-model="draftTo"
        name="overview-to"
        :label="t('dashboard.overview.period.to')"
        :min="draftFrom"
        :max="today"
        :error="errorOf('to')"
        required
      />
      <BaseButton type="submit" variant="primary" :disabled="!draftFrom || !draftTo">
        {{ t('dashboard.overview.period.apply') }}
      </BaseButton>
    </form>
  </div>
</template>
