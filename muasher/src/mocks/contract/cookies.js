export function readCookie(name) {
  const prefix = `${name}=`
  const entry = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix))
  return entry ? decodeURIComponent(entry.slice(prefix.length)) : null
}

export function writeCookie(name, value) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; SameSite=Lax`
}

export function expireCookie(name) {
  document.cookie = `${name}=; path=/; max-age=0; SameSite=Lax`
}
