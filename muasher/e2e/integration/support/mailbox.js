import { existsSync, readFileSync } from 'node:fs'
import process from 'node:process'

const MESSAGE_START = /\] [a-z]+\.DEBUG: From: /
const CODE_LINE = /^(\d{6})\r?$/m
const RECIPIENT_LINE = /^To: (.+?)\r?$/m
const POLL_MS = 250
const WAIT_MS = 20000

function logFiles() {
  return String(process.env.MOASHIR_INTEGRATION_MAIL_LOGS ?? '')
    .split(',')
    .map((file) => file.trim())
    .filter(Boolean)
}

function messagesTo(email) {
  const recipient = email.toLowerCase()
  return logFiles()
    .filter((file) => existsSync(file))
    .flatMap((file) => readFileSync(file, 'utf8').split(MESSAGE_START))
    .filter((message) => message.match(RECIPIENT_LINE)?.[1].trim().toLowerCase() === recipient)
}

export function assertMailLogsConfigured() {
  const files = logFiles()
  if (!files.length || !files.every((file) => existsSync(file))) {
    throw new Error('Set MOASHIR_INTEGRATION_MAIL_LOGS to the log files of the integration backend (see README).')
  }
}

export function mailMark(email) {
  return messagesTo(email).length
}

export async function waitForCode(email, mark) {
  const deadline = Date.now() + WAIT_MS
  while (Date.now() < deadline) {
    const fresh = messagesTo(email).slice(mark)
    const code = fresh.map((message) => message.match(CODE_LINE)?.[1]).findLast(Boolean)
    if (code) return code
    await new Promise((resolve) => setTimeout(resolve, POLL_MS))
  }
  throw new Error('No new one-time code reached the integration mailbox in time.')
}
