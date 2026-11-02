export const FORBIDDEN_TERMS = [
  { term: 'Consultant', pattern: /\bconsultan(?:t|ts|cy)\b/i },
  { term: 'Judge', pattern: /\bjudg(?:e|es|ed|ing)\b/i },
  { term: 'Juror', pattern: /\bjurors?\b/i },
  { term: 'Jury', pattern: /\bjur(?:y|ies)\b/i },
  { term: 'invoice', pattern: /\binvoic(?:e|es|ed|ing)\b/i },
  { term: 'Excellence Bag', pattern: /\b(?:quick |fast )?excellence bag\b|\bfast excellence kit\b/i },
  { term: 'مستشار', pattern: /مستشار/ },
  { term: 'محكم', pattern: /مح[َُِّْ]*ك[َُِّْ]*م/ },
  { term: 'التحكيم', pattern: /تحكيم/ },
  { term: 'فاتورة', pattern: /فاتور|فواتير/ },
]

export function forbiddenTermsIn(text) {
  return FORBIDDEN_TERMS.filter(({ pattern }) => pattern.test(text)).map(({ term }) => term)
}
