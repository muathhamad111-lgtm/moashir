// The list tables whose columns each user may show or hide, and the columns each one offers (the
// first column and the actions always show). The server keeps the same list
// (backend/resources/data/table-columns.json); a test compares the two. `hidden` columns start hidden.
export const TABLE_COLUMNS = Object.freeze({
  'audit-log': { columns: ['event', 'actor', 'subject', 'ipAddress', 'reason'] },
  'award-cycle-picker': { columns: ['status', 'committeeEnd'] },
  'award-cycles': { columns: ['status', 'submission', 'regions', 'counts'] },
  'award-filtering': { columns: ['entity', 'region', 'percent', 'threshold', 'status'] },
  'award-results': { columns: ['region', 'status', 'average', 'regionalRank', 'rank'] },
  'award-secretary-evaluations': { columns: ['status', 'percent', 'submittedAt'] },
  'award-secretary-requests': { columns: ['entity', 'region', 'status', 'committee', 'average'] },
  'bag-evaluator-requests': { columns: ['reference', 'context', 'status', 'assignedAt'] },
  'bag-requests': { columns: ['reference', 'paidAt', 'status', 'evaluator'] },
  'committee-assignments': { columns: ['status', 'dueAt', 'progress', 'percent'] },
  'committee-members': { columns: ['status', 'progress', 'appointedAt'] },
  'contact-inbox': {
    columns: ['sender', 'enquiryType', 'status', 'assignee', 'lastActivityAt', 'entries'],
  },
  'criteria-versions': { columns: ['published', 'scope', 'affected'] },
  'entities-contact': { columns: ['level', 'authorizedPersonName', 'officialEmail', 'status'] },
  'entities-deleted': { columns: ['region', 'deletedAt', 'status'] },
  'entities-email-changes': { columns: ['oldEmail', 'newEmail', 'requestedAt', 'status'] },
  'entities-full': { columns: ['level', 'lastAssessment', 'typeRegion', 'statusRegistered'] },
  'entities-profile-changes': { columns: ['items', 'submittedAt', 'status'] },
  'entity-assessments': { columns: ['path', 'createdAt', 'status', 'result'] },
  'finance-payment-events': {
    columns: ['source', 'eventType', 'payment', 'mode', 'result', 'providerRef'],
  },
  'finance-transactions': {
    columns: ['entity', 'total', 'payment', 'status', 'createdAt', 'receipt', 'beforeVat'],
    hidden: ['payment', 'receipt', 'beforeVat'],
  },
  'kpis-admin': { columns: ['name', 'category', 'value', 'target', 'status', 'published'] },
  'kpis-catalog': { columns: ['key', 'order', 'active'] },
  'legal-versions': { columns: ['status', 'title', 'effectiveFrom', 'updatedAt'] },
  'nda-history': { columns: ['acceptedAt', 'ip', 'roles'] },
  'nda-register': { columns: ['roles', 'version', 'status', 'acceptedAt', 'ip'] },
  'nda-versions': { columns: ['status', 'publishedAt', 'publishedBy', 'acceptances'] },
  'requests-assigned': { columns: ['state', 'assignedAt', 'dueAt', 'progress'] },
  'requests-queue': {
    columns: ['level', 'cycle', 'region', 'status', 'assessor', 'submittedAt', 'dueAt'],
    hidden: ['submittedAt'],
  },
  'settings-abandoned-carts': {
    columns: ['contact', 'purpose', 'startedAt', 'lastSeenAt', 'contactedAt'],
  },
  'settings-permissions': { columns: ['key', 'group', 'roles'] },
  'users-identity-changes': { columns: ['fields', 'requestedAt', 'status'] },
  'users-list': { columns: ['role', 'paths', 'status'] },
})

export const TABLE_COLUMNS_CACHE_KEY = 'muasher.tableColumns'
