export const FIXTURE_VAT_CERTIFICATE_FILE_ID = '8c2d41a7-3b6e-4f10-a5c2-0000000060c1'

export const FIXTURE_VAT_CERTIFICATE_FILE = Object.freeze({
  id: FIXTURE_VAT_CERTIFICATE_FILE_ID,
  purpose: 'vat_certificate',
  name: 'شهادة-التسجيل-الضريبي-نماء.pdf',
  mimeType: 'application/pdf',
  sizeBytes: 248320,
  scanStatus: 'clean',
  createdAt: '2026-07-03T09:10:00Z',
})

function address(buildingNumber, street, district, city, postalCode, additionalNumber) {
  return {
    addressBuildingNumber: buildingNumber,
    addressStreet: street,
    addressDistrict: district,
    addressCity: city,
    addressPostalCode: postalCode,
    addressAdditionalNumber: additionalNumber,
  }
}

const NOT_REGISTERED = Object.freeze({
  vatRegistered: false,
  vatNumber: null,
  vatCertificate: null,
})

export const ENTITY_BILLING_FIXTURE = Object.freeze({
  5001: {
    ...address('7421', 'طريق الملك فهد', 'حي العليا', 'الرياض', '12214', '3365'),
    addressShortCode: 'RRRD7421',
    ...NOT_REGISTERED,
  },
  6001: {
    ...address('2154', 'شارع الأمير سلطان', 'حي الروضة', 'جدة', '23435', '8027'),
    addressShortCode: 'JRDA2154',
    vatRegistered: true,
    vatNumber: '300060010000003',
    vatCertificate: {
      id: FIXTURE_VAT_CERTIFICATE_FILE_ID,
      name: FIXTURE_VAT_CERTIFICATE_FILE.name,
      sizeBytes: FIXTURE_VAT_CERTIFICATE_FILE.sizeBytes,
      uploadedAt: FIXTURE_VAT_CERTIFICATE_FILE.createdAt,
    },
  },
  6002: {
    ...address('4310', 'شارع الملك سعود', 'حي الفيصلية', 'الدمام', '32272', '6612'),
    vatRegistered: true,
    vatNumber: '300060020000003',
    vatCertificate: null,
  },
  6003: {
    ...address('8862', 'شارع الأمير محمد بن فهد', 'حي الخبر الشمالية', 'الخبر', '34428', '2290'),
    ...NOT_REGISTERED,
  },
  6005: {
    ...address('3109', 'طريق الأمير تركي', 'حي النرجس', 'الرياض', '13327', '7745'),
    addressShortCode: 'RNRA3109',
    ...NOT_REGISTERED,
  },
  6006: {
    ...address('5527', 'شارع عثمان بن عفان', 'حي الإسكان', 'بريدة', '52384', '4018'),
    ...NOT_REGISTERED,
  },
})

const NO_BILLING = Object.freeze({
  ...address(null, null, null, null, null, null),
  addressShortCode: null,
  vatRegistered: null,
  vatNumber: null,
  vatCertificate: null,
})

export function billingOf(entityId) {
  return { ...NO_BILLING, ...ENTITY_BILLING_FIXTURE[entityId] }
}
