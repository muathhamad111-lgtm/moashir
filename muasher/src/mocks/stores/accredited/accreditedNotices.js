import { pushNotification } from '../notificationsStore'
import { acceptsEntityContent } from './accreditedStates'

const EXPIRY_WORDING = {
  continue_as_submitted: {
    ar: (number) =>
      `انتهت مهلة استكمال الطلب ${number}، ويعود الطلب إلى المقيّم بالبيانات المتاحة.`,
    en: (number) =>
      `The completion deadline of request ${number} passed. The request returns to the assessor with the data available.`,
  },
  decline: {
    ar: (number) => `انتهت مهلة استكمال الطلب ${number} دون استكماله، فأُغلق الطلب لعدم الاستكمال.`,
    en: (number) =>
      `The completion deadline of request ${number} passed without completion, so the request was closed as incomplete.`,
  },
}

export function announceCompletionExpired(record, outcome) {
  const wording = EXPIRY_WORDING[outcome]
  const title = { ar: 'انتهت مهلة الاستكمال', en: 'Completion deadline passed' }
  const body = { ar: wording.ar(record.number), en: wording.en(record.number) }
  pushNotification({
    code: 'ACC-12',
    recipientRole: 'entity',
    title,
    body,
    link: '/assessment/accredited',
  })
  pushNotification({
    code: 'ACC-12',
    recipientRole: 'request_follower',
    title,
    body,
    link: `/admin/requests/${record.id}`,
  })
  const assessorLink =
    outcome === 'decline' ? `/admin/requests/${record.id}` : `/admin/requests/${record.id}/review`
  pushNotification({ code: 'ACC-12', recipientRole: 'assessor', title, body, link: assessorLink })
}

export function announceRefusedEvidence(record, row) {
  const replaceable = acceptsEntityContent(record.status)
  pushNotification({
    code: 'EVD-02',
    type: 'error',
    recipientRole: 'entity',
    title: { ar: 'رُفض ملف شاهد بعد فحصه', en: 'An evidence file was refused after the scan' },
    body: replaceable
      ? {
          ar: `رُفض ملف شاهد في المعيار ${row.subCode} بعد فحصه. يرجى رفع ملف بديل.`,
          en: `An evidence file of sub-criterion ${row.subCode} was refused after the scan. Please upload a replacement.`,
        }
      : {
          ar: `رُفض ملف شاهد في المعيار ${row.subCode} بعد فحصه، ولا يطلع عليه المقيّم.`,
          en: `An evidence file of sub-criterion ${row.subCode} was refused after the scan. The assessor does not see it.`,
        },
    link: '/assessment/accredited',
  })
}
