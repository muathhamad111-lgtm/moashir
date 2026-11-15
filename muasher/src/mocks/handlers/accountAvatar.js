import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import {
  avatarImageOf,
  isEntityAccount,
  photoIssue,
  removePhoto,
  storePhoto,
} from '../stores/accountAvatars'

function unauthenticated() {
  return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
}

function forbidden() {
  return errorResponse(403, 'forbidden', 'This action is unauthorized.')
}

function personalAccount() {
  const user = currentSessionUser()
  if (!user) return { failure: unauthenticated() }
  if (isEntityAccount(user)) return { failure: forbidden() }
  return { user }
}

export const accountAvatarHandlers = [
  http.get('/api/v1/me/avatar', async () => {
    const user = currentSessionUser()
    if (!user) return unauthenticated()
    const image = await avatarImageOf(user)
    if (!image) return errorResponse(404, 'not_found', 'The requested resource was not found.')
    return new HttpResponse(image.body, {
      status: 200,
      headers: {
        'Content-Type': image.mimeType,
        'Cache-Control': 'no-store, private',
        'X-Content-Type-Options': 'nosniff',
      },
    })
  }),

  http.post('/api/v1/me/avatar', async ({ request }) => {
    await delay(200)
    const { user, failure } = personalAccount()
    if (failure) return failure
    const file = (await request.formData()).get('file')
    const issue = photoIssue(file)
    if (issue) return errorResponse(422, 'validation_failed', issue, { file: [issue] })
    return envelope({ avatar: await storePhoto(user, file) }, {}, { status: 201 })
  }),

  http.delete('/api/v1/me/avatar', async () => {
    await delay(150)
    const { user, failure } = personalAccount()
    if (failure) return failure
    removePhoto(user)
    return envelope({ avatar: null })
  }),
]
