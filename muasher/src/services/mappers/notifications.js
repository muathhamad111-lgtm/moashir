export function toNotification(raw = {}) {
  return {
    id: raw.id ?? null,
    code: raw.code ?? '',
    module: raw.module ?? null,
    category: raw.category ?? 'informational',
    title: raw.title ?? null,
    body: raw.body ?? null,
    link: raw.link ?? null,
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    isRead: raw.isRead === true,
    readAt: raw.readAt ?? null,
    archivedAt: raw.archivedAt ?? null,
    createdAt: raw.createdAt ?? null,
  }
}

function toChannel(raw = {}) {
  return {
    channel: raw.channel ?? null,
    enabled: raw.enabled === true,
    configurable: raw.configurable === true,
  }
}

export function toNotificationPreference(raw = {}) {
  return {
    code: raw.code ?? '',
    module: raw.module ?? null,
    category: raw.category ?? null,
    isMandatory: raw.isMandatory === true,
    channels: (raw.channels ?? []).map(toChannel),
  }
}
