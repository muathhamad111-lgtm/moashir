const PERCENT = 100

function filled(value) {
  return value !== null && value !== undefined && String(value).trim() !== ''
}

function itemsOf(entity) {
  return [
    { key: 'basic_data', isComplete: true, isRequired: true },
    { key: 'account_owner', isComplete: filled(entity.owner?.name), isRequired: true },
    { key: 'governance_grade', isComplete: filled(entity.governanceGrade), isRequired: true },
    { key: 'employees_count', isComplete: filled(entity.employeesCount), isRequired: true },
    {
      key: 'annual_beneficiaries_count',
      isComplete: filled(entity.annualBeneficiariesCount),
      isRequired: true,
    },
    { key: 'unified_number', isComplete: filled(entity.unifiedNumber), isRequired: true },
    { key: 'license_number', isComplete: filled(entity.licenseNumber), isRequired: true },
    { key: 'founded_on', isComplete: filled(entity.foundedOn), isRequired: true },
    { key: 'phone', isComplete: filled(entity.owner?.phone), isRequired: false },
    { key: 'website_url', isComplete: filled(entity.websiteUrl), isRequired: false },
    { key: 'logo', isComplete: Boolean(entity.logo), isRequired: false },
  ]
}

export function profileCompletionOf(entity) {
  const items = itemsOf(entity)
  const required = items.filter((item) => item.isRequired)
  const complete = required.filter((item) => item.isComplete)
  return { percent: Math.floor((complete.length / required.length) * PERCENT), items }
}
