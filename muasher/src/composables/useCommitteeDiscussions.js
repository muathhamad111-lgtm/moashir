import { useI18n } from 'vue-i18n'
import { useFinanceList } from '@/composables/useFinanceList'
import { useToast } from '@/composables/useToast'
import { committeeWorkService } from '@/services/committeeWorkService'
import { COMMITTEE_LIMITS, DISCUSSION_STATUSES } from '@/constants/committee'

export function useCommitteeDiscussions() {
  const { t } = useI18n()
  const toast = useToast()
  const list = useFinanceList({
    fetcher: (params) => committeeWorkService.listDiscussions(params),
    initialFilters: { status: DISCUSSION_STATUSES.OPEN },
    perPage: COMMITTEE_LIMITS.PER_PAGE,
  })

  async function respond(discussion, payload) {
    await committeeWorkService.respond(discussion.id, payload)
    toast.success(t(`award.discussion.toasts.${payload.kind}`))
    await list.load()
  }

  return { ...list, respond }
}
