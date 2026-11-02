export const PROXIED_PATHS = ['/api', '/sanctum']

export function apiProxy(target) {
  if (!target) return undefined
  return Object.fromEntries(PROXIED_PATHS.map((path) => [path, { target, changeOrigin: false }]))
}
