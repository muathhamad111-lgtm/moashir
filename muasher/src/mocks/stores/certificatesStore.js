import { certificatesFixture, feedbackReportsFixture } from '../fixtures/certificates'

export const certificates = certificatesFixture.map((item) => ({ ...item }))
export const feedbackReports = feedbackReportsFixture.map((item) => ({
  ...item,
  items: item.items.map((entry) => ({ ...entry })),
}))
