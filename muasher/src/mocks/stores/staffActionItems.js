import { financeState } from './finance/financeState'
import { findCycle } from './award/cycleStore'
const ROLE_ITEMS = {
  system_admin: [
    {
      key: 'assign_requests',
      count: 3,
      severity: 'warning',
      link: '/admin/requests?status=submitted%2Cawaiting_review_assignment',
    },
    {
      key: 'review_entity_email_changes',
      count: 1,
      severity: 'info',
      link: '/admin/entities/email-changes',
    },
    {
      key: 'handle_privacy_requests',
      count: 1,
      severity: 'warning',
      link: '/admin/privacy-requests',
    },
    {
      key: 'answer_contact_messages',
      count: 2,
      severity: 'warning',
      link: '/admin/contact-messages?status=new',
    },
  ],
  request_follower: [
    {
      key: 'assign_requests',
      count: 3,
      severity: 'warning',
      link: '/admin/requests?status=submitted%2Cawaiting_review_assignment',
    },
    {
      key: 'follow_up_overdue_requests',
      count: 1,
      severity: 'danger',
      link: '/admin/requests?overdue=true',
    },
  ],
  assessor: [
    { key: 'start_reviews', count: 1, severity: 'warning', link: '/admin/requests/assigned' },
    { key: 'continue_reviews', count: 2, severity: 'info', link: '/admin/requests/assigned' },
  ],
  content_officer: [
    {
      key: 'publish_content_changes',
      count: 1,
      severity: 'info',
      link: '/admin/settings/homepage',
    },
    {
      key: 'answer_contact_messages',
      count: 2,
      severity: 'warning',
      link: '/admin/contact-messages?status=new',
    },
  ],
  criteria_officer: [],
  finance_officer: [
    { key: 'record_refunds', count: 1, severity: 'warning', link: '/finance/refunds' },
  ],
  award_secretary: [
    {
      key: 'approve_committee_results',
      count: 1,
      severity: 'warning',
      link: '/admin/award/secretary',
    },
  ],
  filtering_officer: [
    { key: 'decide_filtering', count: 4, severity: 'warning', link: '/admin/award/filtering' },
  ],
}

const SEVERITY_RANK = { danger: 0, warning: 1, info: 2 }

function staffItem(raw) {
  return { ...raw, dueAt: null, context: null }
}

const LATE_AWARD_LINK = '/finance/transactions?purpose=award&status=paid'

function paidAfterWindow(payment) {
  const cycle = findCycle(payment.awardCycleId)
  return (
    payment.purpose === 'award' &&
    payment.status === 'paid' &&
    Boolean(cycle) &&
    payment.paidAt > cycle.submissionEnd
  )
}

function lateAwardItems(roles) {
  if (!roles.includes('finance_officer')) return []
  const count = financeState().payments.filter(paidAfterWindow).length
  return count
    ? [{ key: 'award_paid_after_window', count, severity: 'danger', link: LATE_AWARD_LINK }]
    : []
}

export function staffActionItems(roles) {
  const byKey = new Map()
  roles
    .flatMap((role) => ROLE_ITEMS[role] ?? [])
    .concat(lateAwardItems(roles))
    .forEach((raw) => byKey.set(raw.key, staffItem(raw)))
  return [...byKey.values()].sort(
    (left, right) => SEVERITY_RANK[left.severity] - SEVERITY_RANK[right.severity],
  )
}
