import { FIXTURE_ENTITY_ID } from './assessments'
import { ACCREDITED_FIXTURE_IDS } from './accreditedRequests'

const COMPLETED_ACCREDITED_ID = ACCREDITED_FIXTURE_IDS.completedOwn

const ACCREDITED_LABEL = { ar: 'التقييم المعتمد', en: 'Accredited assessment' }
const LEVEL_LABELS = {
  bronze: { ar: 'المستوى البرونزي', en: 'Bronze level' },
  silver: { ar: 'المستوى الفضي', en: 'Silver level' },
  gold: { ar: 'المستوى الذهبي', en: 'Gold level' },
  diamond: { ar: 'المستوى الماسي', en: 'Diamond level' },
}

function request(id, number) {
  return { id, number, path: 'accredited', pathLabel: ACCREDITED_LABEL }
}

function levelCertificate({
  id,
  entity,
  number,
  level,
  percent,
  requestRef,
  code,
  issuedAt,
  validUntil,
}) {
  return {
    id,
    entityId: entity.id,
    number,
    kind: 'accredited',
    level,
    levelLabel: level ? LEVEL_LABELS[level] : null,
    status: 'issued',
    isTest: false,
    percent,
    request: request(requestRef.id, requestRef.number),
    entity,
    verificationCode: code,
    issuedAt,
    validUntil,
    revokedAt: null,
    revokedReason: null,
    supersededAt: null,
    suspendedAt: null,
    replaces: null,
    hasPdf: true,
  }
}

const LEVEL_ENTITIES = {
  ehsan: {
    id: 6002,
    reference: 'ENT-006002',
    name: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
  },
  ataa: {
    id: 6004,
    reference: 'ENT-006004',
    name: { ar: 'جمعية عطاء للأيتام', en: 'Ataa Orphans Society' },
  },
  ruwad: {
    id: 6005,
    reference: 'ENT-006005',
    name: { ar: 'مؤسسة رواد التعليم', en: 'Ruwad Education Foundation' },
  },
  tamkeen: {
    id: 6006,
    reference: 'ENT-006006',
    name: {
      ar: 'جمعية تمكين لذوي الإعاقة',
      en: 'Tamkeen Society for People with Disabilities',
    },
  },
  athar: {
    id: 7005,
    reference: 'ENT-007005',
    name: { ar: 'جمعية أثر للتنمية المجتمعية', en: 'Athar Community Development Society' },
  },
}

const LEVEL_CERTIFICATES = [
  levelCertificate({
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009110',
    entity: LEVEL_ENTITIES.ehsan,
    number: 'CRT-2026-000010',
    level: 'gold',
    percent: '84.20',
    requestRef: { id: 9310, number: 'ACC-2026-000010' },
    code: 'H2KD-7QWM-PX4T-9ZRA-C6NV-3BYE-5FGL',
    issuedAt: '2026-08-14T08:00:00+00:00',
    validUntil: '2027-08-13',
  }),
  levelCertificate({
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009111',
    entity: LEVEL_ENTITIES.ruwad,
    number: 'CRT-2026-000011',
    level: 'diamond',
    percent: '93.10',
    requestRef: { id: 9311, number: 'ACC-2026-000011' },
    code: 'T8MV-3KQZ-W5HA-2NRC-Y7PD-4EXB-6LGU',
    issuedAt: '2026-03-18T08:00:00+00:00',
    validUntil: '2027-03-17',
  }),
  levelCertificate({
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009112',
    entity: LEVEL_ENTITIES.athar,
    number: 'CRT-2026-000012',
    level: 'bronze',
    percent: '64.40',
    requestRef: { id: 9312, number: 'ACC-2026-000012' },
    code: 'R5PW-8DXK-M2QT-6VZH-A9CN-1EYB-4JGF',
    issuedAt: '2026-07-19T08:00:00+00:00',
    validUntil: '2027-07-18',
  }),
  levelCertificate({
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009113',
    entity: LEVEL_ENTITIES.ataa,
    number: 'CRT-2026-000013',
    level: null,
    percent: '54.00',
    requestRef: { id: 9313, number: 'ACC-2026-000013' },
    code: 'Q4ZN-6WTC-K8MD-3HXA-P2RV-7EYB-9LFG',
    issuedAt: '2026-06-02T08:00:00+00:00',
    validUntil: '2027-06-01',
  }),
  {
    ...levelCertificate({
      id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009109',
      entity: LEVEL_ENTITIES.tamkeen,
      number: 'CRT-2026-000009',
      level: 'gold',
      percent: '81.00',
      requestRef: { id: 9309, number: 'ACC-2026-000009' },
      code: 'D3QF-9MXZ-V6KT-1WHA-N8PC-5RYE-2BGL',
      issuedAt: '2026-04-06T08:00:00+00:00',
      validUntil: '2027-04-05',
    }),
    status: 'revoked',
    revokedAt: '2026-05-10T08:00:00+00:00',
    revokedReason: 'إلغاء بعد مراجعة الأدلة المقدمة.',
  },
]

function verificationUrl(code) {
  return `${window.location.origin}/verify/${code}`
}

export const certificatesFixture = [
  {
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009102',
    entityId: FIXTURE_ENTITY_ID,
    number: 'CRT-2026-000001',
    kind: 'accredited',
    level: 'silver',
    levelLabel: LEVEL_LABELS.silver,
    status: 'issued',
    isTest: false,
    percent: '78.00',
    request: request(COMPLETED_ACCREDITED_ID, 'ACC-2026-000001'),
    entity: {
      id: FIXTURE_ENTITY_ID,
      reference: 'ENT-005001',
      name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
    },
    verificationCode: 'K7Q2-M9XA-4TPE-8RWC-2HZN-6YVB-3DFG',
    issuedAt: '2026-06-22T09:00:00+00:00',
    validUntil: '2027-06-21',
    revokedAt: null,
    revokedReason: null,
    supersededAt: null,
    suspendedAt: null,
    replaces: null,
    hasPdf: true,
  },
  {
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009104',
    entityId: 6001,
    number: 'CRT-2026-000003',
    kind: 'accredited',
    level: 'silver',
    levelLabel: LEVEL_LABELS.silver,
    status: 'issued',
    isTest: false,
    percent: '74.54',
    request: request(ACCREDITED_FIXTURE_IDS.completedEarlier, 'ACC-2026-000003'),
    entity: {
      id: 6001,
      reference: 'ENT-006001',
      name: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
    },
    verificationCode: '9F3K-MQ2T-7XHD-M4PA-Z81C-QW5N-RT6B',
    issuedAt: '2026-09-05T08:00:00+00:00',
    validUntil: '2027-09-04',
    revokedAt: null,
    revokedReason: null,
    supersededAt: null,
    suspendedAt: null,
    replaces: { id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009103', number: 'CRT-2026-000002' },
    hasPdf: true,
  },
  {
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009103',
    entityId: 6001,
    number: 'CRT-2026-000002',
    kind: 'accredited',
    level: 'silver',
    levelLabel: LEVEL_LABELS.silver,
    status: 'superseded',
    isTest: false,
    percent: '74.54',
    request: request(ACCREDITED_FIXTURE_IDS.completedEarlier, 'ACC-2026-000003'),
    entity: {
      id: 6001,
      reference: 'ENT-006001',
      name: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
    },
    verificationCode: 'B4NE-2WQ8-HC7T-KX3M-5RZA-9PDV-1GFY',
    issuedAt: '2026-09-03T08:00:00+00:00',
    revokedAt: null,
    revokedReason: null,
    supersededAt: '2026-09-05T08:00:00+00:00',
    suspendedAt: null,
    replaces: null,
    hasPdf: true,
  },
  ...LEVEL_CERTIFICATES,
].map((certificate) => ({
  ...certificate,
  verificationUrl: verificationUrl(certificate.verificationCode),
}))
