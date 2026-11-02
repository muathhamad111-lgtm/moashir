export function cloneAttachments(source) {
  return (source ?? []).map((item) => ({
    key: item.key ?? null,
    name: { ar: item.name?.ar ?? '', en: item.name?.en ?? '' },
    required: Boolean(item.required),
  }))
}

function isBlank(item) {
  return !item.name.ar.trim() && !item.name.en.trim()
}

export function attachmentsPayload(items) {
  return items
    .filter((item) => !isBlank(item))
    .map((item) => ({
      ...(item.key ? { key: item.key } : {}),
      name: { ar: item.name.ar.trim(), en: item.name.en.trim() },
      required: Boolean(item.required),
    }))
}
