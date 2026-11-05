import { LEGAL_KINDS } from '@/constants/legal'

function sectionOf(raw) {
  return {
    id: raw.id ?? raw.key,
    title: raw.title ?? null,
    blocks: raw.blocks ?? { ar: [], en: [] },
  }
}

export function toLegalDocument(raw = {}) {
  return {
    kind: raw.kind,
    title: raw.title ?? null,
    version: raw.version ?? null,
    updatedAt: raw.publishedAt ?? null,
    effectiveFrom: raw.effectiveFrom ?? null,
    sections: [{ id: 'body', title: null, blocks: raw.body ?? { ar: [], en: [] } }],
  }
}

export function toLegalPage(raw = {}) {
  return {
    kind: LEGAL_KINDS.ACCESSIBILITY,
    title: raw.title ?? null,
    version: null,
    updatedAt: raw.updatedAt ?? raw.publishedAt ?? null,
    effectiveFrom: null,
    sections: (raw.sections ?? []).filter((section) => section.blocks).map(sectionOf),
  }
}
