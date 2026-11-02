import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { useUiStore } from '@/stores/ui'
import { THEMES } from '@/constants/themes'

describe('useBrandLogo', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('serves the green lockup on the dark theme and the dark lockup on the light theme', () => {
    const ui = useUiStore()
    const { themedLogo } = useBrandLogo()

    ui.setTheme(THEMES.dark)
    const onDark = themedLogo.value
    ui.setTheme(THEMES.light)
    const onLight = themedLogo.value

    expect(onDark).not.toBe(onLight)
    expect(onDark).toMatch(/logo-lockup-green/)
    expect(onLight).toMatch(/logo-lockup-dark/)
  })

  it('exposes fixed variants for always-dark and always-light surfaces', () => {
    const { logoOnDark, logoOnLight } = useBrandLogo()
    expect(logoOnDark).toMatch(/logo-lockup-green/)
    expect(logoOnLight).toMatch(/logo-lockup-dark/)
  })
})
