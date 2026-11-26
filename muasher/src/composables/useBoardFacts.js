import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import { formatRiyadhDate } from '@/utils/datetime'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { useOverviewFormats } from './useOverviewFormats'

// The key-value cards of the proposed overview: finance details, the award cycle, the inbox and
// the users. Each builder lists [key, value, tone] rows for one card.
const BUILDERS = Object.freeze({
  finance: (f, h) => [
    ['vatCollected', h.money(f.vatCollected)],
    ['refundsTotal', h.money(f.refundsTotal)],
    ['receiptsIssued', h.count(f.receiptsIssued)],
    ['transfersAwaitingReview', h.count(f.transfersAwaitingReview), 'warning'],
    ['abandonedCarts', h.count(f.abandonedCarts)],
  ],
  award: (a, h) => [
    ['cycle', String(a.cycleYear ?? '—')],
    ['status', a.status ? h.t(`dashboard.board.cycleStates.${toCanonicalStatus(a.status)}`) : '—'],
    ['submissionClosedOn', h.date(a.submissionClosedOn)],
    ['announcementOn', h.date(a.announcementOn)],
    ['applicants', `${h.count(a.qualified)} / ${h.count(a.applicants)}`],
  ],
  inbox: (i, h) => [
    ['received', h.count(i.received)],
    ['awaitingReply', h.count(i.awaitingReply), i.awaitingReply ? 'warning' : ''],
    ['replied', h.count(i.replied), 'success'],
    ['averageReply', h.t('dashboard.board.hours', { count: h.count(i.averageReplyHours) })],
  ],
  users: (u, h) => [
    ['staffActive', h.count(u.staffActive)],
    ['entityAccounts', h.count(u.entityAccounts)],
    ['mfaShare', h.percent(u.mfaShare), 'success'],
    ['locked', h.count(u.locked), u.locked ? 'warning' : ''],
    ['failedLogins', h.count(u.failedLogins)],
  ],
})

export function useBoardFacts(data) {
  const { t, n, locale } = useI18n()
  const format = useOverviewFormats()
  const helpers = {
    t,
    money: format.money,
    percent: format.percent,
    count: (value) => formatNumber(n, value ?? 0),
    date: (value) => (value ? formatRiyadhDate(value, locale.value) : '—'),
  }

  function card(group) {
    return computed(() =>
      BUILDERS[group](data.value[group] ?? {}, helpers).map(([key, value, tone]) => ({
        key,
        label: t(`dashboard.board.facts.${group}.${key}`),
        value,
        tone,
      })),
    )
  }

  return {
    finance: card('finance'),
    award: card('award'),
    inbox: card('inbox'),
    users: card('users'),
  }
}
