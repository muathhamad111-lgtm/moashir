import { rmSync } from 'node:fs'
import { resolve } from 'node:path'
import { BUILD_POSTURES, buildPostureOf, shipsMockLayer } from '../src/utils/build/buildPosture.js'

export const MOCK_ENTRY = 'src/mocks/index.js'
export const MOCK_WORKER_FILE = 'mockServiceWorker.js'
export const DISABLED_MOCKS_ID = '\0moashir-mocks-disabled'
export const DISABLED_MOCKS_SOURCE = 'export async function enableMocking() {}\n'

const MOCKS_SEGMENT = 'mocks'

export function buildPosture() {
  const state = { posture: BUILD_POSTURES.MOCK, building: false, mockEntry: '', outDir: '' }

  return {
    name: 'moashir-build-posture',
    enforce: 'pre',
    configResolved(config) {
      state.posture = buildPostureOf(config.env)
      state.building = config.command === 'build'
      state.mockEntry = resolve(config.root, MOCK_ENTRY)
      state.outDir = resolve(config.root, config.build.outDir)
      if (state.building) config.logger.info(`Moashir build posture: ${state.posture}`)
    },
    async resolveId(source, importer, options) {
      if (shipsMockLayer(state.posture) || !source.includes(MOCKS_SEGMENT)) return null
      const resolved = await this.resolve(source, importer, { ...options, skipSelf: true })
      return resolved?.id === state.mockEntry ? DISABLED_MOCKS_ID : null
    },
    load(id) {
      return id === DISABLED_MOCKS_ID ? DISABLED_MOCKS_SOURCE : null
    },
    closeBundle() {
      if (!state.building || shipsMockLayer(state.posture)) return
      rmSync(resolve(state.outDir, MOCK_WORKER_FILE), { force: true })
    },
  }
}
