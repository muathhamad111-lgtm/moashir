function textOrNull(value) {
  return value === null || value === undefined || value === '' ? null : String(value)
}

function toCertificate(raw) {
  if (!raw?.id) return null
  return {
    id: raw.id,
    name: raw.name ?? '',
    sizeBytes: Number(raw.sizeBytes ?? 0),
    uploadedAt: raw.uploadedAt ?? null,
  }
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
    vatCertificate: toCertificate(raw.vatCertificate),
  }
}

export function billingCompleteOf(raw) {
  return typeof raw?.billingComplete === 'boolean' ? raw.billingComplete : null
}
