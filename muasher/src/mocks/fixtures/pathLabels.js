export const PATH_LABELS = Object.freeze({
  self: { ar: 'التقييم الذاتي', en: 'Self assessment' },
  accredited: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  advanced: { ar: 'التقييم المتقدم (الجائزة)', en: 'Advanced assessment (Award)' },
  bag: { ar: 'حقيبة التميز السريع', en: 'Quick Excellence Kit' },
})

export function pathLabelOf(path) {
  const label = PATH_LABELS[path]
  return label ? { ...label } : null
}
