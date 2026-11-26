import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AWARD_CODES, CYCLE_STATUSES } from '@/constants/advanced'
import { localizedText } from '@/utils/localizedText'

// A cycle opens only with a linked initial assessor, so its requests can be assigned (AJ-28). A cycle opened
// before that rule, or whose links were later removed, is flagged too: its requests cannot be assigned.
const ASSIGNING_STATUSES = [CYCLE_STATUSES.SUBMISSION_OPEN, CYCLE_STATUSES.SUBMISSION_CLOSED]

// What a cycle's stage allows now, shared by its stage buttons (in the header) and its notices.
export function useCycleStage(props) {
  const { t, locale } = useI18n()

  const status = computed(() => props.cycle.status)
  const manage = computed(() => props.cycle.canManage)
  const blockedOpen = computed(
    () => status.value === CYCLE_STATUSES.DRAFT && Boolean(props.openBlock?.cycle),
  )
  const blockedOpenMessage = computed(() => {
    if (!blockedOpen.value) return ''
    const key =
      props.openBlock.code === AWARD_CODES.ANOTHER_OPEN_FOR_SUBMISSION
        ? 'cycles.actions.blockedOpen.staggeredMessage'
        : 'cycles.actions.blockedOpen.message'
    const blocking = props.openBlock.cycle
    return t(key, { name: localizedText(blocking.name, locale.value), year: blocking.year })
  })
  const lacksAssessors = computed(
    () =>
      props.cycle.revealsStaff === true &&
      !(props.cycle.initialAssessors ?? []).some((link) => link.isActive),
  )
  const needsAssessors = computed(
    () => status.value === CYCLE_STATUSES.DRAFT && lacksAssessors.value,
  )
  const assigningBlocked = computed(
    () => ASSIGNING_STATUSES.includes(status.value) && lacksAssessors.value,
  )

  return { status, manage, blockedOpen, blockedOpenMessage, needsAssessors, assigningBlocked }
}
