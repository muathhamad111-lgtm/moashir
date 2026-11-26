<script setup>
import { nextTick, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCalendarMonth } from '@/composables/useCalendarMonth'
import { useCalendarLocale } from '@/composables/useCalendarLocale'
import { useCalendarKeyboard } from '@/composables/useCalendarKeyboard'
import { trapTabKey } from '@/utils/a11y/focusables'
import { isOutsideRange } from '@/utils/calendarMonth'
import { riyadhDateKey } from '@/utils/datetime'
import DateCalendarHeader from './DateCalendarHeader.vue'
import DateCalendarGrid from './DateCalendarGrid.vue'

const props = defineProps({
  selected: { type: String, default: '' },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  label: { type: String, required: true },
  isSheet: { type: Boolean, default: false },
  spanFrom: { type: String, default: '' },
  spanTo: { type: String, default: '' },
})

const emit = defineEmits(['select', 'close'])

const { t } = useI18n()
const titleId = useId()
const rootRef = ref(null)
const gridRef = ref(null)
const today = riyadhDateKey(new Date())
const month = useCalendarMonth({
  selected: () => props.selected,
  min: () => props.min,
  max: () => props.max,
  today,
})
const { activeDate, range, weeks, canShowPrevious, canShowNext } = month
const { isRtl, monthTitle } = useCalendarLocale()

function focusActive() {
  return gridRef.value?.focusActive()
}

function choose(iso) {
  if (!isOutsideRange(iso, range.value)) emit('select', iso)
}

const { onKeydown: onGridKeydown } = useCalendarKeyboard({
  month,
  isRtl,
  select: choose,
  focusActive,
})

function focusIsLost() {
  const focused = document.activeElement
  return !rootRef.value?.contains(focused) || focused.disabled
}

async function showMonth(step) {
  month.moveByMonths(step)
  await nextTick()
  if (focusIsLost()) focusActive()
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    emit('close')
    return
  }
  if (event.key !== 'Tab' || props.isSheet) return
  event.stopPropagation()
  trapTabKey(event, rootRef.value)
}

onMounted(focusActive)
</script>

<template>
  <div
    ref="rootRef"
    class="date-calendar"
    :role="isSheet ? undefined : 'dialog'"
    :aria-modal="isSheet ? undefined : 'true'"
    :aria-label="isSheet ? undefined : t('components.datePicker.dialogLabel', { label })"
    @keydown="onKeydown"
  >
    <DateCalendarHeader
      :title="monthTitle(activeDate)"
      :title-id="titleId"
      :can-show-previous="canShowPrevious"
      :can-show-next="canShowNext"
      @previous="showMonth(-1)"
      @next="showMonth(1)"
    />
    <DateCalendarGrid
      ref="gridRef"
      :weeks="weeks"
      :active-date="activeDate"
      :selected="selected"
      :today="today"
      :range="range"
      :labelled-by="titleId"
      :span-from="spanFrom"
      :span-to="spanTo"
      @choose="choose"
      @keydown="onGridKeydown"
    />
    <slot />
  </div>
</template>
