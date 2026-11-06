import { GOVERNANCE_GRADE } from '@/constants/entityEnums'
import { toWesternDigits } from '@/utils/numerals'

const GRADE_PATTERN = /^\d{1,3}(\.\d{1,2})?$/

export function isValidGovernanceGrade(value) {
  const text = toWesternDigits(value)
  if (text === '') return true
  return (
    GRADE_PATTERN.test(text) &&
    Number(text) >= GOVERNANCE_GRADE.MIN &&
    Number(text) <= GOVERNANCE_GRADE.MAX
  )
}

export function governanceGradePayload(value) {
  const text = toWesternDigits(value)
  return text === '' ? null : Number(text).toFixed(GOVERNANCE_GRADE.DECIMALS)
}

export function formatGovernanceGrade(value) {
  if (value === null || value === undefined || value === '') return ''
  return `${Number(value).toFixed(GOVERNANCE_GRADE.DECIMALS)}%`
}
