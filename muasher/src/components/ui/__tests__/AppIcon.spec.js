import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ICON_GLYPHS } from '@/utils/icons/iconRegistry'

function render(props) {
  return mount(AppIcon, { props })
}

describe('AppIcon', () => {
  it('draws the TTS glyph inline as a decorative SVG by default', () => {
    const svg = render({ name: 'search' })
    expect(svg.element.tagName.toLowerCase()).toBe('svg')
    expect(svg.attributes()).toMatchObject({
      'data-icon': 'search',
      viewBox: '0 0 24 24',
      fill: 'currentColor',
      focusable: 'false',
      'aria-hidden': 'true',
    })
    expect(svg.attributes('role')).toBeUndefined()
    expect(svg.findAll('path').map((path) => path.attributes('d'))).toEqual(ICON_GLYPHS.search)
    expect(svg.classes()).toEqual(['app-icon'])
  })

  it('announces a labelled icon as an image for icon-only controls', () => {
    const svg = render({ name: 'gear', label: 'Settings' })
    expect(svg.attributes('role')).toBe('img')
    expect(svg.attributes('aria-label')).toBe('Settings')
    expect(svg.attributes('aria-hidden')).toBeUndefined()
  })

  it('applies the size tokens and rejects an unknown size', () => {
    expect(render({ name: 'plus', size: 'lg' }).classes()).toContain('app-icon--lg')
    expect(AppIcon.props.size.validator('xl')).toBe(false)
    expect(AppIcon.props.size.validator('sm')).toBe(true)
  })

  it('mirrors directional glyphs in RTL unless the use opts out', () => {
    expect(render({ name: 'arrow-right' }).classes()).toContain('app-icon--mirror')
    expect(render({ name: 'logout' }).classes()).toContain('app-icon--mirror')
    expect(render({ name: 'arrow-right', mirror: false }).classes()).not.toContain(
      'app-icon--mirror',
    )
    expect(render({ name: 'search' }).classes()).not.toContain('app-icon--mirror')
  })

  it('draws a pending icon with its stop-gap glyph under its own name', () => {
    const svg = render({ name: 'award' })
    expect(svg.attributes('data-icon')).toBe('award')
    expect(svg.find('path').attributes('d')).toBe(ICON_GLYPHS.star[0])
  })

  it('draws the brand logos with the generic link glyph until official marks exist', () => {
    const svg = render({ name: 'brand-linkedin' })
    expect(svg.find('path').attributes('d')).toBe(ICON_GLYPHS.link[0])
  })

  it('flips the undo glyph permanently and spins the spinner', () => {
    const undo = render({ name: 'undo' })
    expect(undo.classes()).toContain('app-icon--flip')
    expect(undo.find('path').attributes('d')).toBe(ICON_GLYPHS.refresh[0])
    const spinner = render({ name: 'spinner' })
    expect(spinner.classes()).toContain('app-icon--spin')
    expect(spinner.find('path').attributes('d')).toBe(ICON_GLYPHS['refresh-dots'][0])
  })

  it('draws icons stored by the server in the legacy class format', () => {
    const svg = render({ name: 'fa-solid fa-circle-check' })
    expect(svg.attributes('data-icon')).toBe('check-circle')
    expect(svg.classes()).not.toContain('fa-solid')
  })

  it('renders nothing for an empty or unknown name', () => {
    expect(render({ name: '' }).find('svg').exists()).toBe(false)
    expect(render({ name: null }).find('svg').exists()).toBe(false)
    expect(render({ name: 'not-an-icon' }).find('svg').exists()).toBe(false)
  })
})
