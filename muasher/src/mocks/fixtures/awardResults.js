export const AWARD_REGIONS = [
  { key: 'riyadh', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' } },
  { key: 'makkah', name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' } },
  { key: 'eastern', name: { ar: 'المنطقة الشرقية', en: 'Eastern Region' } },
  { key: 'madinah', name: { ar: 'منطقة المدينة المنورة', en: 'Madinah Region' } },
]

function result(id, region, ar, en, average, initialScore) {
  return {
    assessmentId: id,
    region,
    entityName: { ar, en },
    average,
    initialScore,
    committeeAssessorCount: 4,
    status: 'approved',
    regionalRank: null,
    nationalRank: null,
  }
}

export function seedAwardResults() {
  return [
    result(9401, 'riyadh', 'جمعية البر بالرياض', 'Al-Birr Charity — Riyadh', 92, 88),
    result(9411, 'riyadh', 'مؤسسة نماء التنموية', 'Namaa Development Foundation', 88, 84),
    result(9412, 'riyadh', 'جمعية كنف للأيتام', 'Kanaf Orphans Society', 85, 80),
    result(9413, 'riyadh', 'وقف العطاء الصحي', 'Ataa Health Waqf', 79, 76),
    result(9421, 'makkah', 'جمعية زمزم الصحية', 'Zamzam Health Society', 90, 86),
    result(9422, 'makkah', 'مؤسسة إعمار للتنمية', 'Iemar Development Foundation', 87, 83),
    result(9423, 'makkah', 'جمعية رعاية بمكة', 'Riaya Society — Makkah', 83, 79),
    result(9424, 'makkah', 'وقف السقيا الخيري', 'Suqya Charitable Waqf', 78, 74),
    result(9431, 'eastern', 'جمعية إحسان بالشرقية', 'Ehsan Society — Eastern', 91, 87),
    result(9432, 'eastern', 'مؤسسة تمكين الشرقية', 'Tamkeen Foundation — Eastern', 86, 82),
    result(9433, 'eastern', 'جمعية عطاء الشرقية', 'Ataa Society — Eastern', 80, 77),
    result(9441, 'madinah', 'جمعية طيبة الخيرية', 'Taibah Charitable Society', 89, 85),
    result(9442, 'madinah', 'مؤسسة رواد التعليم', 'Ruwad Education Foundation', 84, 81),
    result(9443, 'madinah', 'وقف المدينة للرعاية', 'Madinah Care Waqf', 77, 73),
  ]
}
