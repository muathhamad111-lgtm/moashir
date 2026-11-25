import { toEntityDocument } from './entityDocuments'

function textOrNull(value) {
  return value === null || value === undefined || value === '' ? null : String(value)
}

export function toBilling(raw) {
  if (!raw) return null
  return {
    addressBuildingNumber: textOrNull(raw.addressBuildingNumber),
    addressStreet: textOrNull(raw.addressStreet),
    addressDistrict: textOrNull(raw.addressDistrict),
    addressCity: textOrNull(raw.addressCity),
    addressPostalCode: textOrNull(raw.addressPostalCode),
    addressAdditionalNumber: textOrNull(raw.addressAdditionalNumber),
    addressShortCode: textOrNull(raw.addressShortCode),
    vatRegistered: typeof raw.vatRegistered === 'boolean' ? raw.vatRegistered : null,
    vatNumber: textOrNull(raw.vatNumber),
    vatCertificate: toEntityDocument(raw.vatCertificate),
  }
}

export function billingCompleteOf(raw) {
  return typeof raw?.billingComplete === 'boolean' ? raw.billingComplete : null
}
