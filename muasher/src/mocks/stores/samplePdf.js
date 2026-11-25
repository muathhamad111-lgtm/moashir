const HEADER = '%PDF-1.4\n'
const FONT = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'

function asciiOnly(text) {
  return String(text)
    .replace(/[^\x20-\x7E]/g, '')
    .replace(/[()\\]/g, '')
}

function objectsFor(title) {
  const stream = `BT /F1 18 Tf 72 760 Td (${asciiOnly(title) || 'Moashir sample document'}) Tj ET`
  return [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    FONT,
    `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`,
  ]
}

/** A small, valid one-page PDF the browser viewer can open, for the mock documents. */
export function samplePdf(title) {
  let body = HEADER
  const offsets = objectsFor(title).map((object, index) => {
    const offset = body.length
    body += `${index + 1} 0 obj\n${object}\nendobj\n`
    return offset
  })
  const xrefAt = body.length
  const entries = offsets.map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`).join('')
  body += `xref\n0 ${offsets.length + 1}\n0000000000 65535 f \n${entries}`
  body += `trailer << /Size ${offsets.length + 1} /Root 1 0 R >>\nstartxref\n${xrefAt}\n%%EOF\n`
  return new TextEncoder().encode(body)
}
