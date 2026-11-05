export const contentMessages = {
  validation_failed: { ar: 'البيانات المرسلة غير صالحة.', en: 'The submitted data is invalid.' },
  unauthenticated: { ar: 'يرجى تسجيل الدخول للمتابعة.', en: 'Please sign in to continue.' },
  forbidden: {
    ar: 'ليست لديك صلاحية لتنفيذ هذا الإجراء.',
    en: 'You do not have permission to perform this action.',
  },
  not_found: { ar: 'المورد المطلوب غير موجود.', en: 'The requested resource was not found.' },
  reason_required: {
    ar: 'يجب كتابة سبب لهذا الإجراء.',
    en: 'A reason is required for this action.',
  },
  'state.transition_illegal': {
    ar: 'هذا الإجراء غير مسموح به في الحالة الحالية.',
    en: 'This action is not allowed in the current state.',
  },
  'cms.section_key_taken': {
    ar: 'يوجد قسم آخر في هذه الصفحة يستخدم المعرّف نفسه.',
    en: 'Another section of this page already uses this key.',
  },
  'cms.limit_reached': {
    ar: 'تم بلوغ الحد الأعلى لعدد العناصر.',
    en: 'The maximum number of entries has been reached.',
  },
  'cms.page_empty': {
    ar: 'لا يوجد في الصفحة قسم مفعّل لنشره.',
    en: 'The page has no enabled section to publish.',
  },
  'cms.page_not_published': { ar: 'هذه الصفحة غير منشورة.', en: 'This page is not published.' },
  'kpi.featured_limit_reached': {
    ar: 'يمكن عرض :limit مؤشرات كحد أقصى في الصفحة الرئيسية.',
    en: 'At most :limit indicators can be shown on the homepage.',
  },
  'kpi.measurement_exists': {
    ar: 'توجد قراءة مسجلة لهذا التاريخ.',
    en: 'A measurement is already recorded for this date.',
  },
  'slug.taken': {
    ar: 'يوجد مؤشر آخر يستخدم هذا العنوان.',
    en: 'Another indicator already uses this address.',
  },
  'order.incomplete': {
    ar: 'يجب أن يتضمن الترتيب الجديد جميع عناصر القائمة مرة واحدة لكل عنصر.',
    en: 'The new order must list every item of the list exactly once.',
  },
  'category.in_use': {
    ar: 'هذا التصنيف مستخدم في مؤشرات. يمكنك تعطيله بدلًا من حذفه، أو نقل مؤشراته أولًا.',
    en: 'This category is used by indicators. Deactivate it instead, or move its indicators first.',
  },
  'type.in_use': {
    ar: 'نوع المؤشر هذا مستخدم في مؤشرات. يمكنك تعطيله بدلًا من حذفه.',
    en: 'This indicator type is used by indicators. Deactivate it instead.',
  },
  'token.unknown': {
    ar: 'اللون المختار ليس ضمن لوحة الألوان المعتمدة.',
    en: 'The selected color is not part of the approved palette.',
  },
  contrast_fail: {
    ar: 'اللون المختار لا يحقق نسبة التباين المطلوبة وفق المستوى AA من معايير WCAG 2.1 في الوضعين الفاتح والداكن.',
    en: 'The selected color does not meet the WCAG 2.1 AA contrast ratio in light and dark mode.',
  },
  'file.content_mismatch': {
    ar: 'نوع الملف غير مسموح أو أن محتواه لا يطابق امتداده.',
    en: 'The file type is not allowed or its content does not match its extension.',
  },
  'file.not_usable': {
    ar: 'الصورة المختارة غير موجودة أو لم تجتز الفحص أو تخص محتوى آخر. يرجى رفعها مرة أخرى.',
    en: 'The selected image does not exist, is not clean, or belongs to other content. Upload it again.',
  },
}

export const contentFieldMessages = {
  required: { ar: 'هذا الحقل مطلوب.', en: 'This field is required.' },
  localized: {
    ar: 'أدخل النص بالعربية أو بالإنجليزية.',
    en: 'Enter the text in Arabic or in English.',
  },
  linkSafe: {
    ar: 'يجب أن يكون الرابط مسارًا داخليًا يبدأ بـ / أو مرساة تبدأ بـ # أو عنوان mailto: أو رابط https://.',
    en: 'The link must be a site path starting with /, an anchor starting with #, a mailto: address or an https:// link.',
  },
  https: { ar: 'يجب أن يبدأ الرابط بـ https://.', en: 'The link must start with https://.' },
  icon: {
    ar: 'يجب أن تكون الأيقونة صنفًا من Font Awesome مثل fa-solid fa-chart-line.',
    en: 'The icon must be a Font Awesome class such as fa-solid fa-chart-line.',
  },
  keyFormat: {
    ar: 'يبدأ المعرّف بحرف إنجليزي صغير ويتكون من أحرف صغيرة وأرقام وشرطات فقط.',
    en: 'The key starts with a lowercase letter and uses lowercase letters, digits, hyphens and underscores only.',
  },
  unknownKey: { ar: 'هذا الحقل ليس من الإعدادات.', en: 'This field is not part of the settings.' },
  kpiNumber: {
    ar: 'يجب أن يكون الرقم بحد أقصى 14 خانة قبل الفاصلة العشرية و4 بعدها.',
    en: 'The number must have at most 14 digits before the decimal point and 4 after it.',
  },
  between: {
    ar: 'يجب أن تكون القيمة بين :min و:max.',
    en: 'The value must be between :min and :max.',
  },
  email: { ar: 'أدخل بريدًا إلكترونيًا صالحًا.', en: 'Enter a valid e-mail address.' },
  futureDate: {
    ar: 'لا يمكن أن يكون تاريخ القراءة في المستقبل.',
    en: 'The measurement date cannot be in the future.',
  },
  imageType: {
    ar: 'يجب أن تكون الصورة بصيغة PNG أو JPG بحجم 5 ميجابايت كحد أقصى.',
    en: 'The image must be a PNG or JPG of at most 5 MB.',
  },
  logoType: {
    ar: 'يجب أن يكون الشعار بصيغة PNG أو SVG بحجم 1 ميجابايت كحد أقصى.',
    en: 'The logo must be a PNG or SVG of at most 1 MB.',
  },
  prohibited: { ar: 'لا يمكن تعديل هذا الحقل من هنا.', en: 'This field cannot be changed here.' },
  metaObject: {
    ar: 'يجب أن تكون خيارات العرض كائنًا بحقول مسماة.',
    en: 'The display options must be an object of named fields.',
  },
  background: {
    ar: 'يتكون مفتاح الخلفية من أحرف إنجليزية صغيرة وأرقام وشرطات فقط.',
    en: 'The background key uses lowercase letters, digits and hyphens only.',
  },
  reasonLength: {
    ar: 'يجب أن يتكون السبب من 10 أحرف على الأقل.',
    en: 'The reason must be at least 10 characters.',
  },
}
