import { Buffer } from 'node:buffer'
import { createHmac } from 'node:crypto'

const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
const STEP_SECONDS = 30
const DIGITS = 6

function base32Bytes(secret) {
  const bits = secret
    .replace(/[\s=]/g, '')
    .toUpperCase()
    .split('')
    .map((char) => BASE32.indexOf(char).toString(2).padStart(5, '0'))
    .join('')
  const bytes = []
  for (let offset = 0; offset + 8 <= bits.length; offset += 8)
    bytes.push(Number.parseInt(bits.slice(offset, offset + 8), 2))
  return Buffer.from(bytes)
}

export function timeStepOf(at = Date.now()) {
  return Math.floor(at / 1000 / STEP_SECONDS)
}

export function totpCode(secret, step = timeStepOf()) {
  const counter = Buffer.alloc(8)
  counter.writeBigUInt64BE(BigInt(step))
  const digest = createHmac('sha1', base32Bytes(secret)).update(counter).digest()
  const offset = digest[digest.length - 1] & 0x0f
  const value = digest.readUInt32BE(offset) & 0x7fffffff
  return String(value % 10 ** DIGITS).padStart(DIGITS, '0')
}

export function millisecondsToNextStep(at = Date.now()) {
  return (timeStepOf(at) + 1) * STEP_SECONDS * 1000 - at
}
