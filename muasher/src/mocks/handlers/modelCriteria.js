import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { publicModelCriteria } from '../stores/criteriaSerializers'

export const modelCriteriaHandlers = [
  http.get('/api/v1/content/model-criteria', async () => {
    await delay(240)
    return envelope(publicModelCriteria())
  }),
]
