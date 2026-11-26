export function toCycleDescription(raw) {
  return raw?.ar || raw?.en ? { ar: raw.ar ?? '', en: raw.en ?? '' } : null
}
