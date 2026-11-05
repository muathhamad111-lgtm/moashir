// The mock service worker keeps the pages it serves in memory only. The browser stops an idle worker (a background
// tab, a sleeping laptop) and restarts it without that list, and from then on every request of the page bypasses the
// mocks and reaches the real API. Announcing the page again whenever it may have been idle keeps the demo modules
// answering. "MOCK_ACTIVATE" is the message msw itself sends when it starts; the worker treats a repeat as a no-op.
const ACTIVATE_MESSAGE = 'MOCK_ACTIVATE'
const ANNOUNCE_INTERVAL_MS = 5000
const RESUME_EVENTS = ['pageshow', 'online', 'focus']

export function keepPageAnnounced({
  target = window,
  page = document,
  serviceWorker = navigator.serviceWorker,
} = {}) {
  const announce = () => serviceWorker?.controller?.postMessage(ACTIVATE_MESSAGE)
  const announceWhenVisible = () => {
    if (page.visibilityState === 'visible') announce()
  }

  page.addEventListener('visibilitychange', announceWhenVisible)
  RESUME_EVENTS.forEach((name) => target.addEventListener(name, announce))
  const timer = target.setInterval(announce, ANNOUNCE_INTERVAL_MS)

  return function stop() {
    target.clearInterval(timer)
    page.removeEventListener('visibilitychange', announceWhenVisible)
    RESUME_EVENTS.forEach((name) => target.removeEventListener(name, announce))
  }
}
