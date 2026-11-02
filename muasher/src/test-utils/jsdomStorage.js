const jsdomWindow = globalThis.jsdom.window

for (const storageName of ['localStorage', 'sessionStorage']) {
  Object.defineProperty(globalThis, storageName, {
    configurable: true,
    get: () => jsdomWindow[storageName],
  })
}
