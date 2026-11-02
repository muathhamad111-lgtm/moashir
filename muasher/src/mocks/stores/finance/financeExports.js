import { HttpResponse } from 'msw'
import { requestLocale } from '../../contract/locale'
import { financeEntityEmails } from '../../fixtures/finance'
import { pushNotification } from '../notificationsStore'
import { financeState, persistFinanceState, receiptOf, refundOf } from './financeState'
import { financeReject } from './financeRejection'
import { filteredTransactions } from './financeQueries'
import { nowIso } from './paymentLifecycle'
import { entityReferenceOf } from './receiptSnapshot'
import { storeFile } from './signedFiles'

export const MOCK_QUEUE_THRESHOLD = 10
const READY_AFTER_MS = 4000
const FORMULA_START = /^[=+\-@\t\r]/
const BYTE_ORDER_MARK = '\uFEFF'

const COLUMN_LABELS = {
  ar: ['المرجع', 'رقم الإيصال', 'تاريخ الإنشاء', 'تاريخ الدفع', 'الخدمة', 'مرجع الجهة', 'اسم الجهة', 'بريد الجهة', 'وسيلة الدفع', 'الحالة', 'السعر قبل الضريبة', 'نسبة الضريبة', 'مبلغ الضريبة', 'الإجمالي', 'العملة', 'تاريخ الاسترداد', 'مبلغ الاسترداد'],
  en: ['Reference', 'Receipt number', 'Created at', 'Paid at', 'Service', 'Entity reference', 'Entity name', 'Entity e-mail', 'Payment method', 'Status', 'Price before VAT', 'VAT rate', 'VAT amount', 'Total', 'Currency', 'Refunded at', 'Refund amount'],
}

function neutralized(value) {
  const text = String(value ?? '')
  const safe = FORMULA_START.test(text) ? `'${text}` : text
  return /[",\n]/.test(safe) ? `"${safe.replaceAll('"', '""')}"` : safe
}

function rowOf(payment, locale) {
  return [
    payment.reference,
    receiptOf(payment.id)?.number ?? '',
    payment.createdAt,
    payment.paidAt ?? '',
    payment.purpose,
    entityReferenceOf(payment.entityId),
    payment.entityName[locale] ?? payment.entityName.ar,
    financeEntityEmails[payment.entityId] ?? '',
    payment.method ?? '',
    payment.status,
    payment.priceBeforeVat,
    payment.vatRate,
    payment.vatAmount,
    payment.total,
    payment.currency,
    payment.refundedAt ?? '',
    refundOf(payment.id)?.amount ?? '',
  ]
}

export function csvOf(payments, locale) {
  const lines = [COLUMN_LABELS[locale], ...payments.map((payment) => rowOf(payment, locale))]
  return `${BYTE_ORDER_MARK}${lines.map((cells) => cells.map(neutralized).join(',')).join('\n')}\n`
}

function exportFileName() {
  const stamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').slice(0, 15)
  return `transactions-${stamp}.csv`
}

function exportRecord(user, rows, status, locale) {
  const record = {
    id: crypto.randomUUID(),
    requestedBy: user.id,
    status,
    rowCount: rows.length,
    locale,
    paymentIds: rows.map((payment) => payment.id),
    createdAt: nowIso(),
    completedAt: status === 'streamed' ? nowIso() : null,
    readyAt: Date.now() + READY_AFTER_MS,
    fileId: null,
  }
  financeState().exports.push(record)
  persistFinanceState()
  return record
}

export function exportTransactions(user, filters, request) {
  const locale = requestLocale(request)
  const rows = filteredTransactions(filters, 'live')
  if (rows.length > MOCK_QUEUE_THRESHOLD) return { queued: exportRecord(user, rows, 'queued', locale) }
  const record = exportRecord(user, rows, 'streamed', locale)
  return {
    stream: new HttpResponse(csvOf(rows, locale), {
      status: 200,
      headers: {
        'Content-Type': 'text/csv; charset=UTF-8',
        'Content-Disposition': `attachment; filename="${exportFileName()}"`,
        'X-Export-Row-Count': String(record.rowCount),
      },
    }),
  }
}

function completeExport(record) {
  const payments = record.paymentIds.map((id) => financeState().payments.find((payment) => payment.id === id)).filter(Boolean)
  const content = csvOf(payments, record.locale)
  const file = storeFile({ purpose: 'finance_export', name: exportFileName(), mimeType: 'text/csv', sizeBytes: content.length, content })
  record.status = 'ready'
  record.completedAt = nowIso()
  record.fileId = file.id
  pushNotification({
    recipientRole: 'finance_officer',
    code: 'FIN-01',
    type: 'info',
    title: { ar: 'ملف التصدير جاهز', en: 'Export ready' },
    body: { ar: 'اكتمل تصدير العمليات المالية وأصبح جاهزًا للتنزيل.', en: 'The financial transactions export is complete and ready to download.' },
    link: `/finance/exports/${record.id}`,
  })
  persistFinanceState()
}

export function progressed(record) {
  if (record.status === 'queued' && Date.now() >= record.readyAt) completeExport(record)
  return record
}

export function readyExportFile(record) {
  if (progressed(record).status !== 'ready') throw financeReject.exportNotReady()
  return record.fileId
}
