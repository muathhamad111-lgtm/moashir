import { http } from '../contract/http'
import { issueXsrfCookie } from '../contract/csrf'
import { noContent } from '../contract/responses'

export const sanctumHandlers = [
  http.get('/sanctum/csrf-cookie', () => {
    issueXsrfCookie()
    return noContent()
  }),
]
