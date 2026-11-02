const MOYASAR_HOSTS = /^https:\/\/([a-z0-9-]+\.)*moyasar\.com\//i

export async function guardMoyasar(page) {
  const leaks = []
  await page.context().route(MOYASAR_HOSTS, (route) => {
    leaks.push(route.request().url())
    return route.abort()
  })
  return leaks
}
