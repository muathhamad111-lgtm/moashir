// Fictional sample values for the live previews only; never real data.
const SAMPLES = {
  entity: { ar: 'جمعية النور الخيرية', en: 'Al Noor Charity' },
  reference: { ar: 'MSH-2026-0142', en: 'MSH-2026-0142' },
  number: { ar: 'ACC-2026-0087', en: 'ACC-2026-0087' },
  name: { ar: 'سارة', en: 'Sara' },
  deadline: { ar: '30 نوفمبر 2026', en: '30 November 2026' },
}

export function fillSamples(text, locale, labelOf) {
  return String(text ?? '').replace(/:([a-z][a-z0-9_]*)/gi, (match, name) => {
    const sample = SAMPLES[name]?.[locale]
    return sample ?? `[${labelOf(name)}]`
  })
}
