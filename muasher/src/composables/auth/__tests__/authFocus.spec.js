import { afterEach, describe, expect, it } from 'vitest'
import { AUTH_AUTOFOCUS, focusArrival, shouldMoveFocus } from '@/composables/auth/authFocus'

function page(html) {
  const section = document.createElement('section')
  section.innerHTML = html
  document.body.append(section)
  return section
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('focusArrival', () => {
  it('focuses a marked field itself', () => {
    const arrived = page(
      `<h1>Sign in</h1><input name="identifier"><input name="password" ${AUTH_AUTOFOCUS}>`,
    )
    focusArrival(arrived)
    expect(document.activeElement).toBe(arrived.querySelector('[name="password"]'))
  })

  it('focuses the first enabled field inside a marked group', () => {
    const arrived = page(
      `<h1>Code</h1><div ${AUTH_AUTOFOCUS}><input class="box" disabled><input class="box"></div>`,
    )
    focusArrival(arrived)
    expect(document.activeElement).toBe(arrived.querySelectorAll('.box')[1])
  })

  it('falls back to the heading when the marked group has no enabled field', () => {
    const arrived = page(`<h1>Code</h1><div ${AUTH_AUTOFOCUS}><input disabled></div>`)
    focusArrival(arrived)
    const heading = arrived.querySelector('h1')
    expect(heading.getAttribute('tabindex')).toBe('-1')
    expect(document.activeElement).toBe(heading)
  })

  it('focuses the heading of a page with no marked field', () => {
    const arrived = page('<h1>Register</h1><input name="name">')
    focusArrival(arrived)
    expect(document.activeElement).toBe(arrived.querySelector('h1'))
  })
})

describe('shouldMoveFocus', () => {
  it('leaves focus that the new page already placed', () => {
    const arrived = page('<input autofocus>')
    arrived.querySelector('input').focus()
    expect(shouldMoveFocus(arrived, true)).toBe(false)
  })

  it('leaves focus that sits outside the card', () => {
    const outside = page('<button>outside</button>').querySelector('button')
    outside.focus()
    expect(shouldMoveFocus(page('<h1>Next</h1>'), false)).toBe(false)
  })

  it('moves focus that was inside the old page or was lost to the body', () => {
    const arrived = page('<h1>Next</h1>')
    expect(shouldMoveFocus(arrived, false)).toBe(true)
    const outside = page('<button>outside</button>').querySelector('button')
    outside.focus()
    expect(shouldMoveFocus(arrived, true)).toBe(true)
  })
})
