import { toValue } from 'vue'
import { DAYS_PER_WEEK } from '@/utils/calendarMonth'

const MONTHS_PER_YEAR = 12
const SELECT_KEYS = Object.freeze(['Enter', ' '])

function monthStep(event, direction) {
  return direction * (event.shiftKey ? MONTHS_PER_YEAR : 1)
}

export function useCalendarKeyboard({ month, isRtl, select, focusActive }) {
  const forward = () => (toValue(isRtl) ? -1 : 1)

  const moves = {
    ArrowRight: () => month.moveByDays(forward()),
    ArrowLeft: () => month.moveByDays(-forward()),
    ArrowDown: () => month.moveByDays(DAYS_PER_WEEK),
    ArrowUp: () => month.moveByDays(-DAYS_PER_WEEK),
    PageUp: (event) => month.moveByMonths(monthStep(event, -1)),
    PageDown: (event) => month.moveByMonths(monthStep(event, 1)),
    Home: () => month.moveToWeekStart(),
    End: () => month.moveToWeekEnd(),
  }

  function onKeydown(event) {
    if (SELECT_KEYS.includes(event.key)) {
      event.preventDefault()
      select(month.activeDate.value)
      return
    }
    const move = moves[event.key]
    if (!move) return
    event.preventDefault()
    move(event)
    focusActive()
  }

  return { onKeydown }
}
