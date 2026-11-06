import { PATH_ENTITY_TEXTS } from '@/constants/paths'

function toLocalized(raw) {
  return raw ? { ar: raw.ar ?? '', en: raw.en ?? '' } : null
}

function toPrice(raw) {
  if (!raw) return null
  return {
    priceBeforeVat: raw.priceBeforeVat ?? null,
    vatRate: raw.vatRate ?? null,
    vatAmount: raw.vatAmount ?? null,
    total: raw.total ?? null,
    currency: raw.currency ?? null,
  }
}

export function toPathSettings(raw = {}) {
  return {
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    reviewSlaDays: raw.reviewSlaDays ?? null,
    reviewSlaLimits: { min: raw.reviewSlaLimits?.min ?? 1, max: raw.reviewSlaLimits?.max ?? 120 },
    uploadLimits: {
      maxFileMb: raw.uploadLimits?.maxFileMb ?? null,
      allowedTypes: [...(raw.uploadLimits?.allowedTypes ?? [])],
      maxFilesPerSub: raw.uploadLimits?.maxFilesPerSub ?? null,
    },
    uploadLimitBounds: {
      maxFileMb: raw.uploadLimitBounds?.maxFileMb ?? 100,
      maxFilesPerSub: raw.uploadLimitBounds?.maxFilesPerSub ?? 50,
    },
    evidenceTypeCatalog: [...(raw.evidenceTypeCatalog ?? [])],
    isPaid: raw.isPaid === true,
    price: toPrice(raw.price),
    entityTexts: Object.fromEntries(
      PATH_ENTITY_TEXTS.map((key) => [key, toLocalized(raw.entityTexts?.[key])]),
    ),
  }
}
