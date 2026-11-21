function toPerson(raw) {
  return raw ? { id: raw.id ?? null, name: raw.name ?? '' } : null
}

function toSender(raw = {}) {
  return {
    name: raw.name ?? '',
    email: raw.email ?? '',
    mobile: raw.mobile ?? null,
    organization: raw.organization ?? null,
    locale: raw.locale ?? null,
  }
}

function toEntity(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    reference: raw.reference ?? '',
    name: raw.name ?? null,
    status: raw.status ?? null,
  }
}

function toEntry(raw = {}) {
  return {
    id: raw.id ?? null,
    kind: raw.kind ?? 'message',
    body: raw.body ?? '',
    author: {
      kind: raw.author?.kind ?? 'sender',
      id: raw.author?.id ?? null,
      name: raw.author?.name ?? '',
    },
    createdAt: raw.createdAt ?? null,
  }
}

export function toInboxMessage(raw = {}) {
  return {
    id: raw.id ?? null,
    reference: raw.reference ?? '',
    status: raw.status ?? 'new',
    enquiryType: raw.enquiryType ?? 'general',
    sender: toSender(raw.sender),
    isLinked: raw.isLinked === true,
    linkedUser: toPerson(raw.linkedUser),
    entity: toEntity(raw.entity),
    assignee: toPerson(raw.assignee),
    entryCount: raw.entryCount ?? null,
    createdAt: raw.createdAt ?? null,
    lastActivityAt: raw.lastActivityAt ?? null,
    repliedAt: raw.repliedAt ?? null,
    closedAt: raw.closedAt ?? null,
    closedBy: toPerson(raw.closedBy),
    entries: (raw.entries ?? []).map(toEntry),
  }
}

export function toInboxStats(raw = {}) {
  return {
    new: raw.new ?? 0,
    inProgress: raw.inProgress ?? 0,
    replied: raw.replied ?? 0,
    closed: raw.closed ?? 0,
  }
}
