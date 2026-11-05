import { rmSync } from 'node:fs'
import { resolve } from 'node:path'
import {
  BUILD_POSTURES,
  BUILD_POSTURE_PROBLEMS,
  buildPostureOf,
  buildPostureProblemOf,
} from '../src/utils/build/buildPosture.js'

export const MOCK_ENTRY = 'src/mocks/index.js'
export const MOCK_WORKER_FILE = 'mockServiceWorker.js'
export const DISABLED_MOCKS_ID = '\0moashir-mocks-disabled'
export const DISABLED_MOCKS_SOURCE = 'export async function enableMocking() {}\n'

const MOCKS_SEGMENT = 'mocks'

const PROBLEM_MESSAGES = Object.freeze({
  [BUILD_POSTURE_PROBLEMS.DEMO_MODULES_IN_REAL_BUILD]:
    'VITE_DEMO_MODULES must be empty in a real-operation build. Set VITE_DEMO_BUILD=true only for a client-demo build, or empty VITE_DEMO_MODULES.',
})

export function assertBuildPosture(env) {
  const problem = buildPostureProblemOf(env)
  if (problem) throw new Error(PROBLEM_MESSAGES[problem])
}

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
      if (!state.building) return
      assertBuildPosture(config.env)
      config.logger.info(`Moashir build posture: ${state.posture}`)
    },
    async resolveId(source, importer, options) {
      if (state.posture !== BUILD_POSTURES.REAL || !source.includes(MOCKS_SEGMENT)) return null
      const resolved = await this.resolve(source, importer, { ...options, skipSelf: true })
      return resolved?.id === state.mockEntry ? DISABLED_MOCKS_ID : null
    },
    load(id) {
      return id === DISABLED_MOCKS_ID ? DISABLED_MOCKS_SOURCE : null
    },
    closeBundle() {
      if (!state.building || state.posture !== BUILD_POSTURES.REAL) return
      rmSync(resolve(state.outDir, MOCK_WORKER_FILE), { force: true })
    },
  }
}
