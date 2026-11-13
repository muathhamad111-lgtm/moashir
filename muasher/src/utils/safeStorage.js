function readFrom(storageName, key) {
  try {
    return window[storageName].getItem(key)
  } catch {
    return null
  }
}

function writeTo(storageName, key, value) {
  try {
    window[storageName].setItem(key, value)
    return true
  } catch {
    return false
  }
}

export function readStored(key) {
  return readFrom('localStorage', key)
}

export function writeStored(key, value) {
  return writeTo('localStorage', key, value)
}

export function readSessionStored(key) {
  return readFrom('sessionStorage', key)
}

export function writeSessionStored(key, value) {
  return writeTo('sessionStorage', key, value)
}
