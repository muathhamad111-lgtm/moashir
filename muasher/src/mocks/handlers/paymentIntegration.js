import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import {
  actorIsSuperAdmin,
  actorReference,
  integrationActor,
  integrationRoute,
  readBody,
} from '../stores/integrations/integrationRejection'
import { fieldErrors } from '../stores/integrations/integrationValidation'
import { throttleTest } from '../stores/integrations/integrationTesting'
import {
  checkAssociationFile,
  paymentResource,
  registerWebhook,
  removeAssociationFile,
  storeAssociationFile,
  testPayment,
  updatePayment,
} from '../stores/integrations/paymentIntegration'

const PATH = '/api/v1/settings/payment'
const FILE_PATH = `${PATH}/apple-pay/association-file`
const FILE_MAX_KILOBYTES = 64
const FILE_TYPES = ['', 'text/plain']
const PRINTABLE_TEXT = /^[\x20-\x7E\r\n\t]+$/

function payer() {
  return actorReference(integrationActor('payment'))
}

function hexOf(buffer) {
  return [...new Uint8Array(buffer)].map((byte) => byte.toString(16).padStart(2, '0')).join('')
}

async function associationFileFrom(form) {
  const file = form.get('file')
  const errors = fieldErrors()
  if (!(file instanceof Blob)) errors.add('file', 'field.required')
  else if (file.size > FILE_MAX_KILOBYTES * 1024)
    errors.add('file', 'field.file_size', { max: FILE_MAX_KILOBYTES })
  else if (!FILE_TYPES.includes(file.type) || !PRINTABLE_TEXT.test(await file.text()))
    errors.add('file', 'field.file_type')
  errors.throwIfAny()
  const bytes = await file.arrayBuffer()
  return { sizeBytes: file.size, sha256: hexOf(await crypto.subtle.digest('SHA-256', bytes)) }
}

export const paymentIntegrationHandlers = [
  http.get(
    PATH,
    integrationRoute(() => {
      payer()
      return envelope(paymentResource())
    }),
  ),

  http.put(
    PATH,
    integrationRoute(async ({ request }) => {
      const actor = payer()
      return envelope(
        updatePayment(await readBody(request), actor, { superAdmin: actorIsSuperAdmin() }),
      )
    }),
  ),

  http.post(
    `${PATH}/test-connection`,
    integrationRoute(async ({ request }) => {
      const actor = payer()
      const body = await readBody(request)
      throttleTest(actor, 'payment:test-connection')
      return envelope(testPayment(body, actor))
    }),
  ),

  http.post(
    `${PATH}/webhook/register`,
    integrationRoute(async ({ request }) => {
      const actor = payer()
      const body = await readBody(request)
      throttleTest(actor, 'payment:webhook')
      return envelope(
        registerWebhook(body, actor, { superAdmin: actorIsSuperAdmin() }),
        {},
        { status: 201 },
      )
    }),
  ),

  http.post(
    FILE_PATH,
    integrationRoute(async ({ request }) => {
      const actor = payer()
      const file = await associationFileFrom(await request.formData())
      return envelope(storeAssociationFile(file, actor), {}, { status: 201 })
    }),
  ),

  http.delete(
    FILE_PATH,
    integrationRoute(() => {
      payer()
      removeAssociationFile()
      return noContent()
    }),
  ),

  http.post(
    `${FILE_PATH}/check`,
    integrationRoute(() => {
      const actor = payer()
      throttleTest(actor, 'payment:file-check')
      return envelope(checkAssociationFile())
    }),
  ),
]
