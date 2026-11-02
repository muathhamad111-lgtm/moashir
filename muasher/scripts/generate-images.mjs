import { readFile, writeFile, mkdir } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const ROOT = resolve(__dirname, '..')
const OUTPUT_DIR = resolve(ROOT, 'public/generated')
const ENV_FILE = resolve(ROOT, '.env.local')
const DEFAULT_MODEL = 'gemini-3-pro-image'
const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models'

const EXT_BY_MIME = {
  'image/png': 'png',
  'image/jpeg': 'jpg',
  'image/jpg': 'jpg',
  'image/webp': 'webp',
}

const STYLE =
  'Modern flat premium vector illustration, clean minimal geometric shapes, ' +
  'institutional and professional tone, Muasher brand palette of teal and ' +
  'green (#3de9ad) with deep teal (#022426) accents on a soft light background, ' +
  'subtle depth, generous whitespace, no text, no words, no logos, no watermarks.'

const PROMPTS = [
  {
    name: 'hero',
    text: `${STYLE} A confident hero scene symbolizing institutional excellence and performance measurement for a Saudi non-profit sector platform: an upward growth chart, a rising bar graph, and a stylized organizational building, conveying maturity and progress. Wide 16:9 composition.`,
  },
  {
    name: 'home-background',
    text: `${STYLE} A very subtle, soft, abstract background texture of faint geometric shapes, gentle waves and light connected dots, extremely low contrast so text remains readable on top, mostly empty light space. Wide 16:9 backdrop.`,
  },
  {
    name: 'evaluation-paths',
    text: `${STYLE} Three parallel forward paths merging toward a goal flag, representing distinct evaluation journeys of increasing rigor and institutional maturity.`,
  },
  {
    name: 'excellence-model',
    text: `${STYLE} A layered model diagram flowing from enablers through creativity to results: gears, a lightbulb, and an ascending line chart connected by arrows.`,
  },
  {
    name: 'how-it-works',
    text: `${STYLE} A clear five-step horizontal journey with connected circular milestones and a checkmark at the end, representing a guided institutional process from registration to continuous improvement.`,
  },
  {
    name: 'fast-excellence-kit',
    text: `${STYLE} A fast improvement toolkit concept: a lightning bolt, a checklist, and a gauge trending upward, conveying quick, focused organizational improvement without heavy evidence.`,
  },
  {
    name: 'leadership',
    text: `${STYLE} An institutional leadership award concept: an elegant medal with a laurel and a subtle podium, conveying prestige and recognition.`,
  },
  {
    name: 'analytics-lenses',
    text: `${STYLE} Analytical lenses concept: several stylized magnifying lenses over a dashboard revealing different colored data layers and insights, conveying multi-perspective analysis.`,
  },
  {
    name: 'kpi-dashboard',
    text: `${STYLE} A premium performance-indicators dashboard: large clean number tiles, a circular progress gauge and a rising trend line, conveying data-driven excellence measurement.`,
  },
  {
    name: 'login',
    text: `${STYLE} A welcoming sign-in scene for a measurement platform: a secure shield with a key and a friendly dashboard, conveying safety and a warm return. Vertical portrait composition.`,
  },
  {
    name: 'register',
    text: `${STYLE} An inviting registration scene for an organization onboarding to a platform: a form with a plus badge and a building joining a network of nodes. Vertical portrait composition.`,
  },
  {
    name: 'error-404',
    text: `${STYLE} A gentle, non-alarming lost-page illustration: a stylized compass and a disconnected map pin pointing back to a safe path, with a subtle "not found" feeling conveyed only through shapes, no text.`,
  },
  {
    name: 'error-403',
    text: `${STYLE} A calm access-restricted illustration: a friendly closed lock and a shield in front of a door, conveying a protected area rather than a hard failure, no text.`,
  },
  {
    name: 'error-500',
    text: `${STYLE} A reassuring server-hiccup illustration: stylized gears and a cloud with a small spanner, conveying a temporary technical issue being fixed, calm and non-alarming, no text.`,
  },
  {
    name: 'empty-state',
    text: `${STYLE} A calm, friendly empty-state illustration: an open empty folder or an empty dashboard with a subtle sparkle, conveying that data will appear soon.`,
  },
]

async function resolveConfig() {
  const fromFile = await loadEnvFileAsync()
  const apiKey = process.env.GEMINI_API_KEY || fromFile.GEMINI_API_KEY || ''
  const model =
    process.env.GEMINI_IMAGE_MODEL || fromFile.GEMINI_IMAGE_MODEL || DEFAULT_MODEL
  return { apiKey, model }
}

async function loadEnvFileAsync() {
  if (!existsSync(ENV_FILE)) return {}
  const parsed = {}
  let raw
  try {
    raw = await readFile(ENV_FILE, 'utf8')
  } catch {
    return parsed
  }
  for (const line of raw.split(/\r?\n/)) {
    const trimmed = line.trim()
    if (!trimmed || trimmed.startsWith('#')) continue
    const eq = trimmed.indexOf('=')
    if (eq === -1) continue
    const key = trimmed.slice(0, eq).trim()
    let value = trimmed.slice(eq + 1).trim()
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1)
    }
    if (key) parsed[key] = value
  }
  return parsed
}

function extractInlineImage(payload) {
  const parts = payload?.candidates?.[0]?.content?.parts
  if (!Array.isArray(parts)) return null
  for (const part of parts) {
    const inline = part?.inlineData ?? part?.inline_data
    if (inline?.data) {
      return { mimeType: inline.mimeType ?? inline.mime_type ?? 'image/png', data: inline.data }
    }
  }
  return null
}

async function generateOne(prompt, model, apiKey) {
  const url = `${API_BASE}/${model}:generateContent?key=${apiKey}`
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ contents: [{ parts: [{ text: prompt.text }] }] }),
  })

  if (!response.ok) {
    const detail = await response.text().catch(() => '')
    throw new Error(`HTTP ${response.status} ${response.statusText} ${detail.slice(0, 300)}`)
  }

  const payload = await response.json()
  const image = extractInlineImage(payload)
  if (!image) {
    throw new Error('No inline image data found in the response candidates.')
  }

  const buffer = Buffer.from(image.data, 'base64')
  const ext = EXT_BY_MIME[image.mimeType] ?? 'png'
  const file = `${prompt.name}.${ext}`
  const target = resolve(OUTPUT_DIR, file)
  await writeFile(target, buffer)
  return { file, bytes: buffer.length, mimeType: image.mimeType }
}

async function main() {
  const { apiKey, model } = await resolveConfig()

  if (!apiKey) {
    process.stdout.write(
      'GEMINI_API_KEY is not set. Add it to .env.local (GEMINI_API_KEY=your_key) ' +
        'or export it in your shell, then run the script again.\n',
    )
    process.exit(1)
  }

  await mkdir(OUTPUT_DIR, { recursive: true })

  process.stdout.write(`Generating ${PROMPTS.length} illustrations with model "${model}".\n`)
  process.stdout.write(`Output directory: ${OUTPUT_DIR}\n\n`)

  const succeeded = []
  const failed = []

  for (const prompt of PROMPTS) {
    process.stdout.write(`- ${prompt.name} ... `)
    try {
      const result = await generateOne(prompt, model, apiKey)
      succeeded.push(result)
      process.stdout.write(`ok -> ${result.file} (${result.bytes} bytes, ${result.mimeType})\n`)
    } catch (error) {
      failed.push({ file: prompt.file, message: error.message })
      process.stdout.write(`failed (${error.message})\n`)
    }
  }

  process.stdout.write(
    `\nSummary: ${succeeded.length} generated, ${failed.length} failed, of ${PROMPTS.length} total.\n`,
  )

  if (succeeded.length) {
    process.stdout.write(
      'Reference these from the CMS section/item image fields as ' +
        '/generated/<file> (e.g. /generated/hero.png).\n',
    )
  }

  if (failed.length) {
    process.exit(1)
  }
}

main().catch((error) => {
  process.stdout.write(`Unexpected error: ${error.message}\n`)
  process.exit(1)
})
