import { findFile, findPayment, persistFinanceState } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { nowIso } from './paymentLifecycle'
import { storeFile } from './signedFiles'

const PDF_BYTES_PER_PAGE = 48213

function fileNameOf(receipt) {
  const payment = findPayment(receipt.paymentId)
  const base = receipt.number ?? `receipt-${payment.reference}-TEST`
  return receipt.status === 'voided' ? `${base}-VOID.pdf` : `${base}.pdf`
}

export function receiptFileOf(receipt) {
  const existing = receipt.pdfFileId ? findFile(receipt.pdfFileId) : null
  if (existing && existing.name === fileNameOf(receipt)) return existing
  const file = storeFile({
    purpose: 'receipt_pdf',
    name: fileNameOf(receipt),
    mimeType: 'application/pdf',
    sizeBytes: PDF_BYTES_PER_PAGE,
  })
  receipt.pdfFileId = file.id
  persistFinanceState()
  return file
}

export function validatedReissueReason(body) {
  const reason = typeof body.reason === 'string' ? body.reason.trim() : ''
  if (!reason) throw financeReject.validation({ reason: [fieldMessage('required')] })
  if (reason.length < 10)
    throw financeReject.validation({ reason: [fieldMessage('min', { min: 10 })] })
  if (reason.length > 1000)
    throw financeReject.validation({ reason: [fieldMessage('max', { max: 1000 })] })
  return reason
}

export function reissueReceipt(receipt) {
  if (receipt.status === 'voided') throw financeReject.receiptVoided()
  receipt.reissueCount += 1
  receipt.lastReissuedAt = nowIso()
  receipt.pdfFileId = null
  persistFinanceState()
  return receipt
}
