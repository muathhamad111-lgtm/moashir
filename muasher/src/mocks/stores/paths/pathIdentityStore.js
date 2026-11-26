import { PATH_LABELS } from '../../fixtures/pathLabels'

// Mirrors the server: path names are fixed labels; each assessment path has an editable description.
export const IDENTITY_PATHS = Object.freeze(['self', 'accredited', 'advanced'])
export const PATH_DESCRIPTION_MAX = 500

const DEFAULT_DESCRIPTIONS = Object.freeze({
  self: {
    ar: 'تقييم اختياري تجريه جهتك بنفسها لقياس جاهزيتها، ولا يمنح مستوى.',
    en: 'An optional assessment your entity runs itself to gauge its readiness; it grants no level.',
  },
  accredited: {
    ar: 'تقييم يجريه مقيّم معتمد ويمنح مستوى التميّز وشهادته. تُحدَّد فترة الطلب تلقائياً من تاريخ إرساله.',
    en: 'An assessment by an accredited assessor that grants the excellence level and its certificate. The request period is set automatically from its submission date.',
  },
  advanced: {
    ar: 'جائزة سنوية للجهات المتميزة، تُعلن نتائجها بعد اكتمال التقييم.',
    en: 'An annual award for excellent entities; results are announced once the assessment is complete.',
  },
})

let identities = null

export function resetPathIdentities() {
  identities = Object.fromEntries(
    IDENTITY_PATHS.map((path) => [path, { description: { ...DEFAULT_DESCRIPTIONS[path] } }]),
  )
}

resetPathIdentities()

export function pathIdentityOf(path) {
  const identity = identities[path]
  return identity
    ? { name: { ...PATH_LABELS[path] }, description: { ...identity.description } }
    : null
}

export function pathIdentities() {
  return IDENTITY_PATHS.map((path) => ({ path, ...pathIdentityOf(path) }))
}

export function writePathIdentity(path, { description }) {
  if (description) identities[path].description = { ...description }
}
