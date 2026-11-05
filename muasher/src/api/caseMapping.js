import { fromCanonicalStatus, toCanonicalStatus } from '@/utils/canonicalStatus'

const CAMEL_IDENTIFIER = /^[a-z][a-zA-Z0-9]*$/
const SNAKE_IDENTIFIER = /^[a-z][a-z0-9]*(?:_[a-z0-9]+)+$/
const BRACKETED_KEY = /^[A-Za-z0-9_]+(?:\[[A-Za-z0-9_]+\])+$/

const OPAQUE_KEY_CONTAINERS = new Set(['matrix', 'metadata', 'old_values', 'new_values'])
const BRIDGED_ENUM_KEYS = new Set(['evaluationType', 'scaleKind'])
const FIELD_PATH_CONTAINERS = new Set(['errors'])

function identifierToSnake(identifier) {
  return CAMEL_IDENTIFIER.test(identifier)
    ? identifier.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`)
    : identifier
}

function identifierToCamel(identifier) {
  return SNAKE_IDENTIFIER.test(identifier)
    ? identifier.replace(/_([a-z0-9])/g, (match, letter) => letter.toUpperCase())
    : identifier
}

function convertKey(key, convertIdentifier) {
  if (BRACKETED_KEY.test(key)) return key.replace(/[A-Za-z0-9_]+/g, convertIdentifier)
  return convertIdentifier(key)
}

export function toSnakeKey(key) {
  return convertKey(key, identifierToSnake)
}

export function toCamelKey(key) {
  return convertKey(key, identifierToCamel)
}

function convertFieldPath(path, convertIdentifier) {
  return path
    .split('.')
    .map((segment) => convertKey(segment, convertIdentifier))
    .join('.')
}

function lastIdentifier(key) {
  return key.replace(/\]$/, '').split(/[[\]]/).pop()
}

export function isStatusKey(internalKey) {
  const identifier = lastIdentifier(internalKey)
  return identifier === 'status' || /^[a-z][a-zA-Z0-9]*Status$/.test(identifier)
}

export function isBridgedEnumKey(internalKey) {
  return BRIDGED_ENUM_KEYS.has(lastIdentifier(internalKey))
}

export function hasCanonicalValue(internalKey) {
  return isStatusKey(internalKey) || isBridgedEnumKey(internalKey)
}

function mapStatusValue(value, convertToken) {
  return Array.isArray(value) ? value.map(convertToken) : convertToken(value)
}

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

function createConverter({ convertIdentifier, convertStatus, internalKeyOf }) {
  function convertFieldErrors(errors) {
    if (!isPlainObject(errors)) return errors
    return Object.fromEntries(
      Object.entries(errors).map(([path, messages]) => [
        convertFieldPath(path, convertIdentifier),
        messages,
      ]),
    )
  }

  function convertEntry(key, child) {
    const nextKey = convertKey(key, convertIdentifier)
    if (OPAQUE_KEY_CONTAINERS.has(key)) return [nextKey, child]
    if (FIELD_PATH_CONTAINERS.has(key)) return [nextKey, convertFieldErrors(child)]
    const converted = convert(child)
    const internalKey = internalKeyOf(key, nextKey)
    return [
      nextKey,
      hasCanonicalValue(internalKey) ? mapStatusValue(converted, convertStatus) : converted,
    ]
  }

  function convert(value) {
    if (Array.isArray(value)) return value.map(convert)
    if (!isPlainObject(value)) return value
    return Object.fromEntries(Object.entries(value).map(([key, child]) => convertEntry(key, child)))
  }

  return convert
}

export const toWire = createConverter({
  convertIdentifier: identifierToSnake,
  convertStatus: toCanonicalStatus,
  internalKeyOf: (originalKey) => originalKey,
})

export const fromWire = createConverter({
  convertIdentifier: identifierToCamel,
  convertStatus: fromCanonicalStatus,
  internalKeyOf: (originalKey, nextKey) => nextKey,
})

export function formDataToWire(formData) {
  const converted = new FormData()
  formData.forEach((value, key) => converted.append(toSnakeKey(key), value))
  return converted
}

export function formDataFromWire(formData) {
  const converted = new FormData()
  formData.forEach((value, key) => converted.append(toCamelKey(key), value))
  return converted
}
