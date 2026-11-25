function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

function wireValue(value) {
  if (value === null || value === undefined) return ''
  if (typeof value === 'boolean') return value ? '1' : '0'
  return value
}

function append(form, key, value) {
  if (isPlainObject(value)) {
    Object.entries(value).forEach(([child, nested]) => append(form, `${key}[${child}]`, nested))
    return
  }
  if (Array.isArray(value)) {
    value.forEach((item) => append(form, `${key}[]`, item))
    return
  }
  if (value instanceof Blob) form.append(key, value, value.name)
  else form.append(key, wireValue(value))
}

/**
 * A multipart body for a payload that carries files: nested objects become bracketed keys, booleans the form values
 * Laravel reads as booleans, and null an empty value the API reads as null.
 */
export function toFormData(payload) {
  const form = new FormData()
  Object.entries(payload).forEach(([key, value]) => append(form, key, value))
  return form
}
