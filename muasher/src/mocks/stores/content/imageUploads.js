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

export async function acceptImage(request, purpose) {
  const form = await request.formData()
  const file = await validateImage(form.get('file'))
  return { form, upload: registerUpload(file, purpose) }
}
