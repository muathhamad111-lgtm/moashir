import { vi } from 'vitest'

const BOUNDARY = /boundary=(?:"([^"]+)"|([^;]+))/i
const DISPOSITION = /name="([^"]*)"(?:; filename="([^"]*)")?/i
const PART_TYPE = /content-type:\s*([^\r\n]+)/i

function partsOf(text, boundary) {
  return text
    .split(`--${boundary}`)
    .slice(1, -1)
    .map((part) => part.replace(/^\r\n/, '').replace(/\r\n$/, ''))
}

function appendPart(form, part) {
  const [head, ...rest] = part.split('\r\n\r\n')
  const [, name, fileName] = DISPOSITION.exec(head) ?? []
  const body = rest.join('\r\n\r\n')
  if (fileName === undefined) form.append(name, body)
  else
    form.append(
      name,
      new File([body], fileName, { type: PART_TYPE.exec(head)?.[1] ?? '' }),
      fileName,
    )
}

/**
 * Node's multipart parser refuses file parts under jsdom, so a mock spec that posts files over HTTP reads the
 * (text) body with this parser instead, into the jsdom FormData the handlers expect.
 */
export function parseMultipartRequestsAsText() {
  const original = Request.prototype.formData
  return vi.spyOn(Request.prototype, 'formData').mockImplementation(async function formData() {
    const match = BOUNDARY.exec(this.headers.get('content-type') ?? '')
    if (!match) return original.call(this)
    const form = new FormData()
    partsOf(await this.text(), match[1] ?? match[2]).forEach((part) => appendPart(form, part))
    return form
  })
}
