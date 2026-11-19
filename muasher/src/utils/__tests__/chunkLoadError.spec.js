import { describe, expect, it } from 'vitest'
import { isChunkLoadError } from '@/utils/chunkLoadError'

describe('isChunkLoadError', () => {
  it.each([
    'Failed to fetch dynamically imported module: https://moashir.example.sa/assets/Mfa-DpmMW1sN.js',
    'Importing a module script failed.',
    'error loading dynamically imported module: https://moashir.example.sa/assets/Otp-Bq1x.js',
    'Unable to preload CSS for /assets/Mfa-C8kL.css',
  ])('recognises the browser message "%s"', (message) => {
    expect(isChunkLoadError(new TypeError(message))).toBe(true)
    expect(isChunkLoadError(message)).toBe(true)
  })

  it.each([
    new Error('Request failed with status code 500'),
    new TypeError("Cannot read properties of undefined (reading 'step')"),
    { message: 42 },
    null,
    undefined,
  ])('ignores any other failure', (error) => {
    expect(isChunkLoadError(error)).toBe(false)
  })
})
