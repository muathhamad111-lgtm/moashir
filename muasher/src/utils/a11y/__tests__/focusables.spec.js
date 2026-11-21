import { afterEach, describe, expect, it } from 'vitest'
import { focusablesIn, trapTabKey } from '@/utils/a11y/focusables'

function render(html) {
  document.body.innerHTML = `<div id="container" tabindex="-1">${html}</div>`
  return document.getElementById('container')
}

function tab({ shiftKey = false } = {}) {
  return new KeyboardEvent('keydown', { key: 'Tab', shiftKey, cancelable: true })
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('focusablesIn', () => {
  it('lists every standard focusable element in document order', () => {
    const container = render(`
      <div id="editor" contenteditable="true"></div>
      <select id="choice"><option>One</option></select>
      <a id="link" href="/">Link</a>
      <textarea id="notes"></textarea>
      <input id="name" />
      <button id="save" type="button">Save</button>
      <summary id="more">More</summary>
      <span id="custom" tabindex="0">Custom</span>
    `)
    expect(focusablesIn(container).map((element) => element.id)).toEqual([
      'editor',
      'choice',
      'link',
      'notes',
      'name',
      'save',
      'more',
      'custom',
    ])
  })

  it('skips disabled, hidden, inert and programmatic-only elements', () => {
    const container = render(`
      <button id="disabled" type="button" disabled>Off</button>
      <fieldset disabled><input id="in-disabled-fieldset" /></fieldset>
      <input id="hidden-type" type="hidden" />
      <input id="hidden" hidden />
      <div inert><button id="inert" type="button">Inert</button></div>
      <a id="anchor-without-href">Anchor</a>
      <button id="minus-one" type="button" tabindex="-1">Skip</button>
      <div id="read-only-text" contenteditable="false"></div>
      <button id="kept" type="button">Kept</button>
    `)
    expect(focusablesIn(container).map((element) => element.id)).toEqual(['kept'])
  })

  it('returns an empty list without a container', () => {
    expect(focusablesIn(null)).toEqual([])
  })
})

describe('trapTabKey', () => {
  it('wraps Tab from the last element to the first and Shift+Tab back', () => {
    const container = render(`
      <input id="first" />
      <select id="middle"><option>One</option></select>
      <textarea id="last"></textarea>
    `)
    document.getElementById('last').focus()
    const forward = tab()
    trapTabKey(forward, container)
    expect(forward.defaultPrevented).toBe(true)
    expect(document.activeElement.id).toBe('first')

    const backward = tab({ shiftKey: true })
    trapTabKey(backward, container)
    expect(backward.defaultPrevented).toBe(true)
    expect(document.activeElement.id).toBe('last')
  })

  it('lets Tab move normally between inner elements', () => {
    const container = render('<input id="first" /><select id="middle"></select><input id="last" />')
    document.getElementById('first').focus()
    const event = tab()
    trapTabKey(event, container)
    expect(event.defaultPrevented).toBe(false)
  })

  it('keeps the focus on the container when nothing inside can be focused', () => {
    const container = render('<p>Read-only content</p>')
    const event = tab()
    trapTabKey(event, container)
    expect(event.defaultPrevented).toBe(true)
    expect(document.activeElement).toBe(container)
  })
})
