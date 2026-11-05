import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { keepPageAnnounced } from '@/mocks/keepPageAnnounced'

function fakePage(visibilityState = 'visible') {
  const page = new EventTarget()
  page.visibilityState = visibilityState
  return page
}

function fakeWindow() {
  const target = new EventTarget()
  target.setInterval = (callback, delay) => setInterval(callback, delay)
  target.clearInterval = (timer) => clearInterval(timer)
  return target
}

let postMessage
let serviceWorker
let stop

beforeEach(() => {
  vi.useFakeTimers()
  postMessage = vi.fn()
  serviceWorker = { controller: { postMessage } }
})

afterEach(() => {
  stop?.()
  stop = undefined
  vi.useRealTimers()
})

describe('keepPageAnnounced', () => {
  it('announces the page to the mock worker every five seconds', () => {
    stop = keepPageAnnounced({ target: fakeWindow(), page: fakePage(), serviceWorker })

    vi.advanceTimersByTime(5000)
    vi.advanceTimersByTime(5000)

    expect(postMessage).toHaveBeenCalledTimes(2)
    expect(postMessage).toHaveBeenCalledWith('MOCK_ACTIVATE')
  })

  it('announces the page as soon as it is visible again', () => {
    const page = fakePage('hidden')
    stop = keepPageAnnounced({ target: fakeWindow(), page, serviceWorker })

    page.dispatchEvent(new Event('visibilitychange'))
    expect(postMessage).not.toHaveBeenCalled()

    page.visibilityState = 'visible'
    page.dispatchEvent(new Event('visibilitychange'))
    expect(postMessage).toHaveBeenCalledOnce()
  })

  it.each(['pageshow', 'online', 'focus'])('announces the page on %s', (name) => {
    const target = fakeWindow()
    stop = keepPageAnnounced({ target, page: fakePage(), serviceWorker })

    target.dispatchEvent(new Event(name))

    expect(postMessage).toHaveBeenCalledWith('MOCK_ACTIVATE')
  })

  it('does nothing while no worker controls the page', () => {
    const target = fakeWindow()
    stop = keepPageAnnounced({ target, page: fakePage(), serviceWorker: { controller: null } })

    target.dispatchEvent(new Event('focus'))
    vi.advanceTimersByTime(5000)

    expect(postMessage).not.toHaveBeenCalled()
  })

  it('stops announcing once stopped', () => {
    const target = fakeWindow()
    const page = fakePage()
    keepPageAnnounced({ target, page, serviceWorker })()

    target.dispatchEvent(new Event('focus'))
    page.dispatchEvent(new Event('visibilitychange'))
    vi.advanceTimersByTime(10000)

    expect(postMessage).not.toHaveBeenCalled()
  })
})
