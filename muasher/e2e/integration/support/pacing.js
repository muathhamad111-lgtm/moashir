const WINDOWS = {
  mfa: { limit: 5, windowMs: 60 * 1000 },
  signIn: { limit: 20, windowMs: 60 * 1000 },
  passwordReset: { limit: 9, windowMs: 10 * 60 * 1000 },
}
const MARGIN_MS = 1500
const history = { mfa: [], signIn: [], passwordReset: [] }

export async function paced(name, cost = 1) {
  const { limit, windowMs } = WINDOWS[name]
  for (;;) {
    const now = Date.now()
    history[name] = history[name].filter((at) => now - at < windowMs)
    if (history[name].length + cost <= limit) break
    await new Promise((resolve) =>
      setTimeout(resolve, history[name][0] + windowMs + MARGIN_MS - now),
    )
  }
  for (let index = 0; index < cost; index += 1) history[name].push(Date.now())
}
