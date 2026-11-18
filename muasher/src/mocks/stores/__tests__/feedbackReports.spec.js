import { beforeEach, describe, expect, it } from 'vitest'
import { feedbackReportOf } from '@/mocks/stores/feedbackReports'
import { findAssessment, resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { ACCREDITED_FIXTURE_IDS } from '@/mocks/fixtures/accreditedRequests'
import { feedbackNotes } from '@/mocks/fixtures/certificates'
import { formatHijriDate } from '@/utils/datetime'

const NAMAA_REQUEST_ID = ACCREDITED_FIXTURE_IDS.completedEarlier

function reportOfNamaa() {
  return feedbackReportOf(findAssessment(NAMAA_REQUEST_ID), null)
}

function subOf(report, subId) {
  return report.criteria.flatMap((main) => main.subs).find((sub) => sub.subId === subId)
}

describe('feedback report mock', () => {
  beforeEach(() => resetAssessmentsStore())

  it('shows the figures of the request result and certificate', () => {
    const report = reportOfNamaa()
    expect(report).toMatchObject({ total: '6112.50', maxTotal: '8200.00', percent: '74.54' })
    expect(report.criteria[0]).toMatchObject({ earned: '742.00', weight: '1000.00' })
    expect(report.criteria[0].percent).toBe('74.20')
    expect(subOf(report, 601)).toMatchObject({
      earned: '240.00',
      weight: '300.00',
      percent: '80.00',
    })
  })

  it('dates the approval in Hijri from its own completion date', () => {
    const { completedAt, completedOnHijri } = reportOfNamaa()
    expect(completedOnHijri).toEqual({
      ar: formatHijriDate(completedAt, 'ar'),
      en: formatHijriDate(completedAt, 'en'),
    })
    expect(completedOnHijri.ar).toContain('20')
  })

  it('has an assessor note for every sub-criterion of the tree', () => {
    const subIds = reportOfNamaa().criteria.flatMap((main) => main.subs.map((sub) => sub.subId))
    expect(subIds).toHaveLength(19)
    expect(Object.keys(feedbackNotes).map(Number).sort()).toEqual([...subIds].sort())
    expect(subOf(reportOfNamaa(), 601).strengths).toContain('التحول الرقمي')
  })
})
