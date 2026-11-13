import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { CERTIFICATE_LEVELS_FIXTURE } from '../fixtures/certificateLevels'
import { certificateSettingsResource } from '../stores/certificateSettingsStore'

const LEVELS_LATENCY_MS = 120

function publicLevels() {
  const { levelThresholds } = certificateSettingsResource()
  return {
    levels: CERTIFICATE_LEVELS_FIXTURE.levels.map((entry) => ({
      ...entry,
      minPercent: levelThresholds[entry.level] ?? entry.minPercent,
    })),
    participationBelow: levelThresholds.bronze ?? CERTIFICATE_LEVELS_FIXTURE.participationBelow,
  }
}

export const certificateLevelsHandlers = [
  http.get('/api/v1/content/certificate-levels', async () => {
    await delay(LEVELS_LATENCY_MS)
    return envelope(publicLevels())
  }),
]
