import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { PATH_GUIDES_FIXTURE } from '../fixtures/pathGuides'
import { pathTextsOf } from '../stores/accredited/pathSettingsStore'

const GUIDE_LATENCY_MS = 150

export const pathGuidesHandlers = [
  http.get('/api/v1/content/assessment-paths/:path', async ({ params }) => {
    await delay(GUIDE_LATENCY_MS)
    const guide = PATH_GUIDES_FIXTURE[params.path]
    if (!guide) return errorResponse(404, 'not_found', 'Not found.')
    return envelope({ ...guide, entityTexts: pathTextsOf(params.path) })
  }),
]
