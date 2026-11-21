const ARABIC_FORMS = ['zero', 'one', 'two', 'few', 'many', 'other']
const ARABIC_FORM_COUNT = ARABIC_FORMS.length
const arabicRules = new Intl.PluralRules('ar')

export function arabicPluralIndex(choice, choicesLength, orgRule) {
  if (choicesLength !== ARABIC_FORM_COUNT) return orgRule(choice, choicesLength)
  return ARABIC_FORMS.indexOf(arabicRules.select(Math.abs(Number(choice))))
}

export const PLURAL_RULES = Object.freeze({ ar: arabicPluralIndex })
