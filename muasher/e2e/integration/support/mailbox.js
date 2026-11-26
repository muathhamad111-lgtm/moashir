import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'
import process from 'node:process'

const MESSAGE_START = /\] [a-z]+\.DEBUG: From: /
const CODE_LINE = /^(?:[^\d\n]*:\s*)?(\d{6})\r?$/m
const RECIPIENT_LINE = /^To: (.+?)\r?$/m
const POLL_MS = 250
const WAIT_MS = 20000

const MAIL_LOG_FILE = /^mail(-\d{4}-\d{2}-\d{2})?\.log$/

function logSources() {
  return String(process.env.MOASHIR_INTEGRATION_MAIL_LOGS ?? '')
    .split(',')
    .map((file) => file.trim())
    .filter(Boolean)
}

function filesOf(source) {
  if (!existsSync(source)) return []
  if (!statSync(source).isDirectory()) return [source]
  return readdirSync(source)
    .filter((name) => MAIL_LOG_FILE.test(name))
    .map((name) => join(source, name))
}

function logFiles() {
  return logSources().flatMap(filesOf)
}

function messagesTo(email) {
  const recipient = email.toLowerCase()
  return logFiles()
    .flatMap((file) => readFileSync(file, 'utf8').split(MESSAGE_START))
    .filter((message) => message.match(RECIPIENT_LINE)?.[1].trim().toLowerCase() === recipient)
}

export function assertMailLogsConfigured() {
  const sources = logSources()
  if (!sources.length || !sources.every((source) => existsSync(source))) {
    throw new Error(
      'Set MOASHIR_INTEGRATION_MAIL_LOGS to the log files of the integration backend (see README).',
    )
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

const INVITATION_LINK = /\/auth\/invitations\/([A-Za-z0-9_-]+)/

export async function waitForInvitationToken(email, mark) {
  const deadline = Date.now() + WAIT_MS
  while (Date.now() < deadline) {
    const token = messagesTo(email)
      .slice(mark)
      .map((message) => message.match(INVITATION_LINK)?.[1])
      .findLast(Boolean)
    if (token) return token
    await new Promise((resolve) => setTimeout(resolve, POLL_MS))
  }
  throw new Error('No invitation reached the integration mailbox in time.')
}

const RESET_LINK = /\/auth\/reset-password\/([A-Za-z0-9_-]+)/

export async function waitForResetToken(email, mark) {
  const deadline = Date.now() + WAIT_MS
  while (Date.now() < deadline) {
    const token = messagesTo(email)
      .slice(mark)
      .map((message) => message.match(RESET_LINK)?.[1])
      .findLast(Boolean)
    if (token) return token
    await new Promise((resolve) => setTimeout(resolve, POLL_MS))
  }
  throw new Error('No password reset link reached the integration mailbox in time.')
}
