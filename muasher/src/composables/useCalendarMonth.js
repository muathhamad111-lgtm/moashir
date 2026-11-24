import { computed, ref, toValue } from 'vue'
import {
  addDays,
  addMonths,
  clampToRange,
  endOfWeek,
  firstOfMonth,
  isAfterMax,
  isBeforeMin,
  isCalendarIso,
  lastOfMonth,
  monthGrid,
  startOfWeek,
} from '@/utils/calendarMonth'

function boundOf(value) {
  const iso = toValue(value)
  return isCalendarIso(iso) ? iso : ''
}

export function useCalendarMonth({ selected, min, max, today }) {
  const range = computed(() => ({ min: boundOf(min), max: boundOf(max) }))
  const activeDate = ref(clampToRange(boundOf(selected) || toValue(today), range.value))

  const weeks = computed(() => monthGrid(activeDate.value))
  const canShowPrevious = computed(
    () => !isBeforeMin(lastOfMonth(addMonths(activeDate.value, -1)), range.value.min),
  )
  const canShowNext = computed(
    () => !isAfterMax(firstOfMonth(addMonths(activeDate.value, 1)), range.value.max),
  )

  function moveTo(iso) {
    activeDate.value = clampToRange(iso, range.value)
  }

  return {
    activeDate,
    range,
    weeks,
    canShowPrevious,
    canShowNext,
    moveTo,
    moveByDays: (days) => moveTo(addDays(activeDate.value, days)),
    moveByMonths: (months) => moveTo(addMonths(activeDate.value, months)),
    moveToWeekStart: () => moveTo(startOfWeek(activeDate.value)),
    moveToWeekEnd: () => moveTo(endOfWeek(activeDate.value)),
  }
}
