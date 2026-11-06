import { existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  DISABLED_MOCKS_ID,
  DISABLED_MOCKS_SOURCE,
  MOCK_ENTRY,
  MOCK_WORKER_FILE,
  assertBuildPosture,
  buildPosture,
} from '../buildPosture'

const roots = []

function projectRoot() {
  const root = mkdtempSync(join(tmpdir(), 'moashir-posture-'))
  mkdirSync(join(root, 'dist'))
  writeFileSync(join(root, 'dist', MOCK_WORKER_FILE), 'self.addEventListener("fetch", () => {})')
  roots.push(root)
  return root
}

function resolvedConfig(root, env, command = 'build') {
  return { root, env, command, build: { outDir: 'dist' }, logger: { info: vi.fn() } }
}

function started(env, command) {
  const root = projectRoot()
  const plugin = buildPosture()
  plugin.configResolved(resolvedConfig(root, env, command))
  return { plugin, root }
}

function resolverContext(root) {
  return {
    resolve: async (source) => ({
      id: source === '@/mocks' ? resolve(root, MOCK_ENTRY) : resolve(root, 'src', source),
    }),
  }
}

afterEach(() => {
  roots.splice(0).forEach((root) => rmSync(root, { recursive: true, force: true }))
})

describe('build posture plugin', () => {
  it('replaces the mock layer and removes the mock worker in a real-operation build', async () => {
    const { plugin, root } = started({ VITE_API_BASE_URL: '/api/v1' })
    const id = await plugin.resolveId.call(resolverContext(root), '@/mocks', 'src/main.js', {})
    expect(id).toBe(DISABLED_MOCKS_ID)
    expect(plugin.load(DISABLED_MOCKS_ID)).toBe(DISABLED_MOCKS_SOURCE)
    plugin.closeBundle()
    expect(existsSync(join(root, 'dist', MOCK_WORKER_FILE))).toBe(false)
  })

  it('leaves other modules to the normal resolution', async () => {
    const { plugin, root } = started({ VITE_API_BASE_URL: '/api/v1' })
    expect(await plugin.resolveId.call(resolverContext(root), '@/router', 'src/main.js', {})).toBe(
      null,
    )
    expect(
      await plugin.resolveId.call(resolverContext(root), './mocks-free', 'src/main.js', {}),
    ).toBe(null)
    expect(plugin.load('src/router/index.js')).toBe(null)
  })

  it.each([
    ['a client-demo build', { VITE_API_BASE_URL: '/api/v1', VITE_DEMO_BUILD: 'true' }],
    ['the full mock build', {}],
  ])('keeps the mock layer and the worker in %s', async (_, env) => {
    const { plugin, root } = started(env)
    expect(await plugin.resolveId.call(resolverContext(root), '@/mocks', 'src/main.js', {})).toBe(
      null,
    )
    plugin.closeBundle()
    expect(existsSync(join(root, 'dist', MOCK_WORKER_FILE))).toBe(true)
  })

  it('stops a real-operation build that still lists demo modules', () => {
    const plugin = buildPosture()
    const config = resolvedConfig(projectRoot(), {
      VITE_API_BASE_URL: '/api/v1',
      VITE_DEMO_MODULES: 'bag',
    })
    expect(() => plugin.configResolved(config)).toThrow(/VITE_DEMO_MODULES must be empty/)
    expect(() =>
      assertBuildPosture({ VITE_API_BASE_URL: '/api/v1', VITE_DEMO_MODULES: '' }),
    ).not.toThrow()
  })

  it('never stops the development server', () => {
    const plugin = buildPosture()
    const config = resolvedConfig(
      projectRoot(),
      { VITE_API_BASE_URL: '/api/v1', VITE_DEMO_MODULES: 'bag' },
      'serve',
    )
    expect(() => plugin.configResolved(config)).not.toThrow()
    expect(config.logger.info).not.toHaveBeenCalled()
  })

  it('names the posture in the build log', () => {
    const plugin = buildPosture()
    const config = resolvedConfig(projectRoot(), {
      VITE_API_BASE_URL: '/api/v1',
      VITE_DEMO_BUILD: 'true',
    })
    plugin.configResolved(config)
    expect(config.logger.info).toHaveBeenCalledWith('Moashir build posture: demo')
  })
})
