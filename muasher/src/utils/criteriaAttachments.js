function textOf(content) {
  return { ar: content?.ar ?? '', en: content?.en ?? '' }
}

export function cloneAttachments(source) {
  return (source ?? []).map((item) => ({
    key: item.key ?? null,
    name: textOf(item.name),
    guidance: textOf(item.guidance),
    required: Boolean(item.required),
    allowedTypes: [...(item.allowedTypes ?? [])],
    maxFiles: item.maxFiles ? String(item.maxFiles) : '',
  }))
}

function isBlank(text) {
  return !text.ar.trim() && !text.en.trim()
}

function trimmed(text) {
  return { ar: text.ar.trim(), en: text.en.trim() }
}

function payloadOf(item) {
  return {
    ...(item.key ? { key: item.key } : {}),
    name: trimmed(item.name),
    guidance: isBlank(item.guidance) ? null : trimmed(item.guidance),
    required: Boolean(item.required),
    allowedTypes: item.allowedTypes.length ? [...item.allowedTypes] : null,
    maxFiles: item.maxFiles === '' ? null : Number(item.maxFiles),
  }
}

// True when the editor rows differ from the record's attachments (an added, removed or edited row).
export function attachmentsEdited(items, source) {
  return JSON.stringify(items) !== JSON.stringify(cloneAttachments(source))
}

export function attachmentsPayload(items) {
  return items.filter((item) => !isBlank(item.name)).map(payloadOf)
}

const ROW_ERROR_PATHS = {
  row: '',
  nameAr: '.name.ar',
  nameEn: '.name.en',
  guidanceAr: '.guidance.ar',
  guidanceEn: '.guidance.en',
  allowedTypes: '.allowedTypes',
  maxFiles: '.maxFiles',
}

function errorsAt(errors, prefix) {
  return Object.fromEntries(
    Object.entries(ROW_ERROR_PATHS).map(([field, suffix]) => [
      field,
      errors[`${prefix}${suffix}`] ?? errors[`${prefix}${suffix}.0`] ?? '',
    ]),
  )
}

function dottedPaths(errors) {
  return Object.fromEntries(
    Object.entries(errors ?? {}).map(([path, message]) => [
      path.replace(/\[(\d+)\]/g, '.$1'),
      message,
    ]),
  )
}

export function attachmentRowErrors(errors, items) {
  const byPath = dottedPaths(errors)
  let sentIndex = -1
  return items.map((item) => {
    if (isBlank(item.name)) return errorsAt({}, '')
    sentIndex += 1
    return errorsAt(byPath, `attachments.${sentIndex}`)
  })
}
