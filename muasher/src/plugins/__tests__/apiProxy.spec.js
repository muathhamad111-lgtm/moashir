import { describe, expect, it } from 'vitest'
import { apiProxy } from '../../../vite-plugins/apiProxy'

describe('development API proxy', () => {
  it('is off unless a target is given', () => {
    expect(apiProxy(undefined)).toBeUndefined()
    expect(apiProxy('')).toBeUndefined()
  })

  it('forwards the API and Sanctum paths to the target and keeps the browser host', () => {
    expect(apiProxy('http://127.0.0.1:8010')).toEqual({
      '/api': { target: 'http://127.0.0.1:8010', changeOrigin: false },
      '/sanctum': { target: 'http://127.0.0.1:8010', changeOrigin: false },
    })
  })
})
