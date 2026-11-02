export const entityMessages = {
  unauthenticated: { ar: 'يرجى تسجيل الدخول للمتابعة.', en: 'Please sign in to continue.' },
  forbidden: {
    ar: 'ليست لديك صلاحية لتنفيذ هذا الإجراء.',
    en: 'You do not have permission to perform this action.',
  },
  not_found: { ar: 'العنصر المطلوب غير موجود.', en: 'The requested resource was not found.' },
  validation_failed: { ar: 'البيانات المرسلة غير صحيحة.', en: 'The submitted data is invalid.' },
  rate_limited: {
    ar: 'عدد الطلبات كبير. يرجى المحاولة لاحقًا.',
    en: 'Too many requests. Please try again later.',
  },
  account_deactivated: { ar: 'هذا الحساب غير نشط.', en: 'This account is not active.' },
  'state.transition_illegal': {
    ar: 'لا يمكن تنفيذ هذا الإجراء في الحالة الحالية.',
    en: 'This action is not allowed in the current state.',
  },
  'entity.deleted': {
    ar: 'هذه الجهة محذوفة، وسجلاتها مجمّدة حتى تُستعاد الجهة.',
    en: 'This entity was deleted. Its records are frozen until the entity is restored.',
  },
  'entity.open_requests_confirmation_required': {
    ar: 'لدى هذه الجهة طلبات مفتوحة. راجع القائمة وأكّد الحذف للمتابعة.',
    en: 'This entity has open requests. Review the list and confirm the deletion to continue.',
  },
  'entity.not_soft_deleted': {
    ar: 'لا يمكن الحذف النهائي إلا لجهة موجودة في قسم الجهات المحذوفة.',
    en: 'Only an entity in the Deleted entities section can be deleted permanently.',
  },
  'entity.verification_cooldown': {
    ar: 'أُرسل تذكير بالتحقق قبل قليل. يرجى المحاولة لاحقًا.',
    en: 'A verification reminder was sent recently. Please try again later.',
  },
  'confirmation.mismatch': {
    ar: 'نص التأكيد المكتوب لا يطابق الرقم المرجعي للجهة.',
    en: 'The typed confirmation does not match the entity reference.',
  },
  'disclaimer.required': {
    ar: 'يجب الإقرار بأن الحذف النهائي لا يمكن التراجع عنه.',
    en: 'You must acknowledge that permanent deletion cannot be undone.',
  },
  'email_change.pending_approval': {
    ar: 'يوجد طلب تغيير للبريد الإلكتروني الرسمي بانتظار الاعتماد.',
    en: 'An official e-mail change is already awaiting approval.',
  },
  'email_change.address_taken': {
    ar: 'لم يعد بالإمكان استخدام هذا البريد الإلكتروني. يرجى اختيار بريد آخر.',
    en: 'This e-mail address can no longer be used. Please choose another one.',
  },
  'registration.terms_unavailable': {
    ar: 'التسجيل غير متاح حاليًا. يرجى المحاولة لاحقًا.',
    en: 'Registration is not available at the moment. Please try again later.',
  },
  'export.too_large': {
    ar: 'حجم التصدير كبير جدًا. يرجى تضييق عوامل التصفية والمحاولة مرة أخرى.',
    en: 'The export is too large. Narrow the filters and try again.',
  },
  'otp.invalid': { ar: 'رمز التحقق غير صحيح.', en: 'The verification code is incorrect.' },
  'field.required': { ar: 'هذا الحقل مطلوب.', en: 'This field is required.' },
  'field.reason_min': {
    ar: 'يجب ألا يقل السبب عن :min أحرف.',
    en: 'The reason must be at least :min characters.',
  },
  'field.email': {
    ar: 'يجب أن يكون البريد الإلكتروني صحيحًا.',
    en: 'The e-mail address must be valid.',
  },
  'field.email_taken': {
    ar: 'قيمة حقل البريد الإلكتروني الجديد مستخدمة مسبقًا.',
    en: 'The new email has already been taken.',
  },
  'field.new_email_same': {
    ar: 'يجب أن يختلف البريد الإلكتروني الجديد عن البريد الحالي.',
    en: 'The new e-mail must be different from the current one.',
  },
  'field.city_not_in_region': {
    ar: 'اختر مدينة مفعّلة تابعة للمنطقة المحددة.',
    en: 'Choose an active city of the selected region.',
  },
  'field.governance_grade': {
    ar: 'يجب أن تكون درجة الحوكمة رقمًا بين 0 و100 بمنزلتين عشريتين على الأكثر.',
    en: 'The governance grade must be a number between 0 and 100 with at most two decimals.',
  },
  'field.phone': {
    ar: 'صيغة رقم الجوال غير صحيحة.',
    en: 'The mobile number field format is invalid.',
  },
  'field.terms_not_current': {
    ar: 'يجب قبول النسخة الحالية من الشروط والأحكام.',
    en: 'You must accept the current version of the terms and conditions.',
  },
  'field.terms_accepted': {
    ar: 'يجب الموافقة على الشروط والأحكام.',
    en: 'The terms acceptance field must be accepted.',
  },
  'field.password_min': {
    ar: 'يجب ألا تقل كلمة المرور عن 12 حرفًا.',
    en: 'The password field must be at least 12 characters.',
  },
  'field.password_confirmed': {
    ar: 'تأكيد كلمة المرور غير مطابق.',
    en: 'The password field confirmation does not match.',
  },
  'field.founding_year': {
    ar: 'سنة التأسيس غير صحيحة.',
    en: 'The founding year is invalid.',
  },
  'field.count': {
    ar: 'يجب أن يكون العدد رقمًا صحيحًا غير سالب.',
    en: 'The count must be a non-negative whole number.',
  },
  'field.invalid_choice': {
    ar: 'القيمة المختارة غير صحيحة.',
    en: 'The selected value is invalid.',
  },
  'field.city_code_taken': {
    ar: 'رمز المدينة مستخدم مسبقًا في هذه المنطقة.',
    en: 'The city code has already been taken in this region.',
  },
}

export const entityNotices = {
  registrationOtpSent: {
    ar: 'إذا كان بالإمكان التسجيل بهذا البريد الإلكتروني فقد أرسلنا إليه رمز التحقق.',
    en: 'If this e-mail can be registered, a verification code has been sent to it.',
  },
  emailChangeOtpSent: {
    ar: 'أرسلنا رمز التحقق إلى البريد الإلكتروني الجديد.',
    en: 'A verification code has been sent to the new e-mail address.',
  },
}
