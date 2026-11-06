import { findBankAccount, financeState } from './financeState'

const WIDTH = 720
const HEIGHT = 900
const MARGIN = 48
const LINE = 64
const PAPER = '#ffffff'
const INK = '#14302d'
const MUTED = '#5b6f6c'
const RULE = '#d7e3e1'
const TITLE_FONT = 'bold 34px Tajawal, sans-serif'
const LABEL_FONT = '22px Tajawal, sans-serif'
const VALUE_FONT = 'bold 26px Montserrat, Tajawal, sans-serif'
const NOTE_FONT = '20px Tajawal, sans-serif'
const PDF_HEADER = '%PDF-1.4\n'

function transferOfFile(fileId) {
  return financeState().transfers.find((transfer) => transfer.proofFileId === fileId) ?? null
}

function paymentOf(transfer) {
  return financeState().payments.find((payment) => payment.id === transfer.paymentId) ?? null
}

function receiptLines(file) {
  const transfer = transferOfFile(file.id)
  const payment = transfer ? paymentOf(transfer) : null
  const account = transfer ? findBankAccount(transfer.bankAccountId) : null
  if (!payment || !account) return null
  return {
    date: transfer.submittedAt.slice(0, 10),
    amount: `${payment.total} SAR`,
    iban: account.iban.replace(/(.{4})/g, '$1 ').trim(),
    beneficiary: account.accountHolderName,
    reference: payment.reference,
  }
}

function drawRow(context, label, value, row) {
  const y = MARGIN + LINE * (row + 2)
  context.font = LABEL_FONT
  context.fillStyle = MUTED
  context.textAlign = 'right'
  context.fillText(label, WIDTH - MARGIN, y)
  context.font = VALUE_FONT
  context.fillStyle = INK
  context.textAlign = 'left'
  context.fillText(value, MARGIN, y)
  context.fillStyle = RULE
  context.fillRect(MARGIN, y + LINE / 3, WIDTH - MARGIN * 2, 1)
}

function drawReceipt(context, lines) {
  context.fillStyle = PAPER
  context.fillRect(0, 0, WIDTH, HEIGHT)
  context.direction = 'rtl'
  context.fillStyle = INK
  context.font = TITLE_FONT
  context.textAlign = 'right'
  context.fillText('إشعار تحويل بنكي (مثال)', WIDTH - MARGIN, MARGIN + LINE / 2)
  drawRow(context, 'التاريخ', lines.date, 0)
  drawRow(context, 'المبلغ', lines.amount, 1)
  drawRow(context, 'إلى الآيبان', lines.iban, 2)
  drawRow(context, 'المستفيد', lines.beneficiary.en, 3)
  drawRow(context, 'وصف التحويل', lines.reference, 4)
  context.font = NOTE_FONT
  context.fillStyle = MUTED
  context.textAlign = 'center'
  context.fillText('صورة توضيحية — ليست إيصالًا حقيقيًا', WIDTH / 2, HEIGHT - MARGIN)
}

function canvasBlob(canvas, type) {
  return new Promise((resolve) => canvas.toBlob(resolve, type))
}

async function imageSample(file, lines) {
  if (typeof document === 'undefined') return null
  const canvas = document.createElement('canvas')
  canvas.width = WIDTH
  canvas.height = HEIGHT
  const context = canvas.getContext?.('2d')
  if (!context || typeof canvas.toBlob !== 'function') return null
  drawReceipt(context, lines)
  const blob = await canvasBlob(canvas, file.mimeType)
  return blob ? new Uint8Array(await blob.arrayBuffer()) : null
}

function escapePdf(text) {
  return text.replace(/[()\\]/g, (character) => `\\${character}`)
}

function pdfSample(lines) {
  const rows = [
    'Bank transfer notice (example)',
    `Date: ${lines.date}`,
    `Amount: ${lines.amount}`,
    `To IBAN: ${lines.iban}`,
    `Beneficiary: ${lines.beneficiary.en}`,
    `Transfer description: ${lines.reference}`,
    'Illustrative image - not a real receipt',
  ]
  const text = rows
    .map(
      (row, index) =>
        `BT /F1 ${index === 0 ? 20 : 14} Tf 60 ${760 - index * 40} Td (${escapePdf(row)}) Tj ET`,
    )
    .join('\n')
  const objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
    `<< /Length ${text.length} >>\nstream\n${text}\nendstream`,
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
  ]
  let body = PDF_HEADER
  const offsets = objects.map((object, index) => {
    const offset = body.length
    body += `${index + 1} 0 obj\n${object}\nendobj\n`
    return offset
  })
  const xref = body.length
  const entries = offsets.map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`).join('')
  body += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n${entries}`
  body += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`
  return body
}

export async function transferProofSample(file) {
  if (file.purpose !== 'transfer_proof') return null
  const lines = receiptLines(file)
  if (!lines) return null
  if (file.mimeType === 'application/pdf') return pdfSample(lines)
  return imageSample(file, lines)
}
