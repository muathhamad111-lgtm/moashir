import { assessmentReject } from '../assessments/assessmentRejection'
import { dispatchQueuedJob } from '../mockQueue'
import { pushNotification } from '../notificationsStore'
import { findFile } from '../finance/financeState'
import { signedDownloadOf, storeFile } from '../finance/signedFiles'
import { DAY_MS } from './dates'
import { nowIso, presentEvidence } from './accreditedRecords'

const BUNDLE_SYNC_MAX_BYTES = 2 * 1024 * 1024
const BUNDLE_BUILD_DELAY_MS = 2500
const BUNDLE_TTL_MS = DAY_MS
const EMPTY_ZIP = new Uint8Array([0x50, 0x4b, 0x05, 0x06, ...Array.from({ length: 18 }, () => 0)])

function cleanEvidence(record) {
  return presentEvidence(record).filter((row) => row.scanStatus === 'clean')
}

function markReady(bundle) {
  const at = nowIso()
  Object.assign(bundle, {
    status: 'ready',
    readyAt: at,
    expiresAt: new Date(Date.now() + BUNDLE_TTL_MS).toISOString(),
  })
}

function announceReady(record, bundle, requester) {
  pushNotification({
    code: 'EVD-01',
    recipientRole: requester.roles[0] ?? 'system_admin',
    title: { ar: 'حزمة الشواهد جاهزة للتنزيل', en: 'The evidence bundle is ready to download' },
    body: {
      ar: `حزمة شواهد الطلب ${record.number} جاهزة، وتبقى متاحة لمدة 24 ساعة.`,
      en: `The evidence bundle of request ${record.number} is ready and stays available for 24 hours.`,
    },
    link: `/admin/requests/${record.id}/evidence-bundles/${bundle.id}`,
  })
}

export function requestBundle(user, record) {
  const files = cleanEvidence(record)
  if (!files.length) throw assessmentReject.coded(409, 'evidence.bundle_empty')
  const sizeBytes = files.reduce((total, row) => total + row.sizeBytes, 0)
  const bundle = {
    id: crypto.randomUUID(),
    requesterId: user.id,
    status: 'preparing',
    fileCount: files.length,
    sizeBytes,
    storedFileId: null,
    createdAt: nowIso(),
    readyAt: null,
    expiresAt: null,
  }
  record.bundles.push(bundle)
  if (sizeBytes <= BUNDLE_SYNC_MAX_BYTES) {
    markReady(bundle)
    return { bundle, queued: false }
  }
  dispatchQueuedJob(() => {
    markReady(bundle)
    announceReady(record, bundle, user)
  }, BUNDLE_BUILD_DELAY_MS)
  return { bundle, queued: true }
}

export function bundleOf(record, bundleId) {
  const bundle = record.bundles.find((item) => item.id === bundleId) ?? null
  if (bundle?.status === 'ready' && new Date(bundle.expiresAt).getTime() <= Date.now())
    bundle.status = 'expired'
  return bundle
}

function storedZipOf(record, bundle) {
  const stored = bundle.storedFileId ? findFile(bundle.storedFileId) : null
  if (stored) return stored
  const file = storeFile({
    purpose: 'evidence_bundle',
    name: `${record.number}-evidence.zip`,
    mimeType: 'application/zip',
    sizeBytes: bundle.sizeBytes,
    content: EMPTY_ZIP,
  })
  bundle.storedFileId = file.id
  return file
}

export function bundleDownload(user, record, bundle) {
  if (bundle.status !== 'ready') throw assessmentReject.coded(409, 'evidence.bundle_not_ready')
  return signedDownloadOf(user, storedZipOf(record, bundle))
}
