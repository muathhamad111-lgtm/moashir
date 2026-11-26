import { CMS_IMAGE_RULES } from '@/constants/cms'
import { contentReject } from './contentRejection'
import { createFieldErrors } from './contentValidation'
import { hasMatchingContent, registerUpload } from './publicFiles'

export async function validateImage(file, rules = CMS_IMAGE_RULES, messageKey = 'imageType') {
  const errors = createFieldErrors()
  errors.check(
    file instanceof File && rules.mimeTypes.includes(file.type) && file.size <= rules.maxBytes,
    'file',
    messageKey,
  )
  errors.throwIfAny()
  if (!(await hasMatchingContent(file))) throw contentReject.fileContentMismatch()
  return file
}

const UNSAFE_SVG =
  /<script|<foreignObject|<!DOCTYPE|<!ENTITY|<\?xml-stylesheet|\son[a-z]+\s*=|javascript:|@import|href\s*=\s*["'](?!#|data:image\/(?:png|jpeg);)/i

async function assertSafeSvg(file) {
  if (file.type !== 'image/svg+xml') return
  if (UNSAFE_SVG.test(await file.text())) throw contentReject.unsafeSvg()
}

export async function acceptImage(request, purpose, { rules, messageKey } = {}) {
  const form = await request.formData()
  const file = await validateImage(form.get('file'), rules, messageKey)
  await assertSafeSvg(file)
  return { form, upload: registerUpload(file, purpose) }
}
