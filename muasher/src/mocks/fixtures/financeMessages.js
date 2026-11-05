export const financeMessages = {
  validation_failed: { ar: 'البيانات المرسلة غير صالحة.', en: 'The submitted data is invalid.' },
  unauthenticated: { ar: 'يرجى تسجيل الدخول للمتابعة.', en: 'Please sign in to continue.' },
  forbidden: {
    ar: 'ليست لديك صلاحية لتنفيذ هذا الإجراء.',
    en: 'You do not have permission to perform this action.',
  },
  not_found: {
    ar: 'تعذّر العثور على المورد المطلوب.',
    en: 'The requested resource was not found.',
  },
  service_unavailable: {
    ar: 'الخدمة غير متاحة مؤقتًا.',
    en: 'The service is temporarily unavailable.',
  },
  reason_required: {
    ar: 'يلزم إدخال سبب لهذا الإجراء.',
    en: 'A reason is required for this action.',
  },
  'state.transition_illegal': {
    ar: 'هذا الإجراء غير مسموح به في الحالة الحالية.',
    en: 'This action is not allowed in the current state.',
  },
  'entity.deleted': {
    ar: 'حساب الجهة محذوف، ولا يمكن تنفيذ هذا الإجراء.',
    en: 'The entity account is deleted, so this action is not possible.',
  },
  'integration.not_configured': {
    ar: 'لم يُضبط هذا التكامل بعد. احفظ بيانات الربط أولًا.',
    en: 'This integration is not configured yet. Save its credentials first.',
  },
  provider_unavailable: {
    ar: 'هذه الخدمة غير متاحة مؤقتًا. يرجى المحاولة لاحقًا.',
    en: 'This service is temporarily unavailable. Please try again later.',
  },
  'payment.price_not_configured': {
    ar: 'لم يُحدَّد سعر هذه الخدمة بعد. يرجى المحاولة لاحقًا.',
    en: 'The price of this service is not set yet. Please try again later.',
  },
  'payment.entity_required': {
    ar: 'يقتصر الدفع للخدمات على حسابات الجهات.',
    en: 'Only an entity account can pay for a service.',
  },
  'payment.entity_not_active': {
    ar: 'يجب أن يكون حساب الجهة نشطًا للدفع مقابل خدمة.',
    en: 'The entity account must be active to pay for a service.',
  },
  'payment.checkout_in_progress': {
    ar: 'يجري تجهيز عملية الدفع. يرجى المحاولة بعد لحظات.',
    en: 'This payment is being prepared. Please try again in a moment.',
  },
  'payment.binding_mismatch': {
    ar: 'مرجع الدفع هذا لا يخص أمر الدفع الحالي.',
    en: 'This payment reference does not belong to this payment order.',
  },
  'payment.provider_payment_not_found': {
    ar: 'تعذّر العثور على عملية الدفع لدى بوابة الدفع.',
    en: 'The payment could not be found at the payment gateway.',
  },
  'payment.verification_rejected': {
    ar: 'عملية الدفع التي أعادتها البوابة لا تطابق أمر الدفع هذا.',
    en: 'The payment returned by the gateway does not match this payment order.',
  },
  'payment.provider_payment_id_required': {
    ar: 'أدخل مرجع الدفع لدى البوابة لتسجيل العملية مدفوعة.',
    en: 'Enter the gateway payment reference to mark the payment as paid.',
  },
  'payment.provider_payment_id_in_use': {
    ar: 'مرجع الدفع هذا مرتبط بعملية دفع أخرى.',
    en: 'This gateway payment reference is already linked to another payment.',
  },
  'idempotency_key.missing': {
    ar: 'أرسل ترويسة Idempotency-Key فريدة (UUID) مع هذا الطلب.',
    en: 'Send a unique Idempotency-Key header (UUID) with this request.',
  },
  'idempotency_key.reused': {
    ar: 'استُخدم مفتاح Idempotency-Key هذا لطلب مختلف.',
    en: 'This Idempotency-Key was already used for a different request.',
  },
  'purpose.not_available': {
    ar: 'لا يمكن دفع هذه الخدمة عبر المنصة.',
    en: 'This service cannot be paid through the platform.',
  },
  'accredited.request_open': {
    ar: 'لدى جهتكم طلب تقييم معتمد مفتوح. أكملوه قبل سداد رسوم طلب جديد.',
    en: 'Your entity already has an open accredited assessment request. Complete it before paying for a new one.',
  },
  'accredited.payment_being_processed': {
    ar: 'تجري معالجة سداد رسوم التقييم المعتمد، وسيُفتح طلبكم خلال وقت قصير.',
    en: 'Your accredited assessment payment is being processed. Your request will open shortly.',
  },
  'refund.amount_must_equal_total': {
    ar: 'يُسجَّل الاسترداد الكامل فقط. يجب أن يساوي المبلغ إجمالي الدفعة :total.',
    en: 'Only full refunds can be recorded. The amount must equal the payment total of :total.',
  },
  'refund.date_before_payment': {
    ar: 'لا يمكن أن يسبق تاريخ الاسترداد تاريخ الدفع.',
    en: 'The refund date cannot be before the payment date.',
  },
  'refund.already_recorded': {
    ar: 'سُجّل استرداد لهذه الدفعة مسبقًا.',
    en: 'A refund is already recorded for this payment.',
  },
  'receipt.voided': {
    ar: 'أُلغي إيصال الدفع هذا ولا يمكن إعادة إصداره.',
    en: 'This payment receipt was voided and cannot be reissued.',
  },
  'receipt.not_issued': {
    ar: 'لم يصدر إيصال دفع لهذه الدفعة.',
    en: 'No payment receipt has been issued for this payment.',
  },
  'finance_export.not_ready': {
    ar: 'ملف التصدير غير جاهز بعد.',
    en: 'This export is not ready yet.',
  },
  'file.content_mismatch': {
    ar: 'نوع الملف غير مسموح به أو لا يطابق محتواه امتداده.',
    en: 'The file type is not allowed or its content does not match its extension.',
  },
  'file.infected': {
    ar: 'رُفض الملف في فحص البرمجيات الضارة.',
    en: 'The file was rejected by the malware scan.',
  },
  'file.scan_failed': {
    ar: 'تعذّر فحص الملف من البرمجيات الضارة. يرجى المحاولة لاحقًا.',
    en: 'The file could not be scanned for malware. Please try again later.',
  },
  'file.not_clean': {
    ar: 'هذا الملف غير متاح للتنزيل.',
    en: 'This file is not available for download.',
  },
  'signature.invalid': { ar: 'رابط التنزيل غير صالح.', en: 'This download link is not valid.' },
  'signature.expired': {
    ar: 'انتهت صلاحية رابط التنزيل. اطلب رابطًا جديدًا.',
    en: 'This download link has expired. Request a new one.',
  },
  'document.renderer_unavailable': {
    ar: 'يتعذّر إنشاء المستند حاليًا. يرجى المحاولة لاحقًا.',
    en: 'The document cannot be generated at the moment. Please try again later.',
  },
}

export const financeFieldMessages = {
  required: { ar: 'هذا الحقل مطلوب.', en: 'This field is required.' },
  invalid: { ar: 'القيمة المدخلة غير صالحة.', en: 'The value is not valid.' },
  min: { ar: 'يجب ألا يقل النص عن :min حرفًا.', en: 'The text must be at least :min characters.' },
  max: {
    ar: 'يجب ألا يزيد النص على :max حرفًا.',
    en: 'The text may not be greater than :max characters.',
  },
  date: {
    ar: 'أدخل تاريخًا صحيحًا بصيغة YYYY-MM-DD.',
    en: 'Enter a valid date in the format YYYY-MM-DD.',
  },
  date_future: { ar: 'لا يمكن أن يكون التاريخ بعد اليوم.', en: 'The date cannot be after today.' },
  date_order: {
    ar: 'يجب أن يكون تاريخ النهاية بعد تاريخ البداية أو مساويًا له.',
    en: 'The end date must be on or after the start date.',
  },
  amount: {
    ar: 'أدخل المبلغ بخانتين عشريتين، مثل 1150.00.',
    en: 'Enter the amount with two decimals, for example 1150.00.',
  },
  file_type: {
    ar: 'يُسمح بملفات PDF وJPG وPNG فقط.',
    en: 'Only PDF, JPG and PNG files are allowed.',
  },
  file_size: {
    ar: 'يجب ألا يزيد حجم الملف على :max ميغابايت.',
    en: 'The file may not be greater than :max MB.',
  },
  price_format: {
    ar: 'أدخل السعر بخانتين عشريتين وبحد أقصى 7 خانات صحيحة، مثل 1500.00.',
    en: 'Enter the price with two decimals and at most 7 integer digits, for example 1500.00.',
  },
  price_positive: {
    ar: 'يجب أن يكون السعر أكبر من صفر.',
    en: 'The price must be greater than zero.',
  },
  vat_format: {
    ar: 'أدخل نسبة بين 0 و100 بخانتين عشريتين كحد أقصى.',
    en: 'Enter a rate between 0 and 100 with at most two decimals.',
  },
  between: {
    ar: 'يجب أن تكون القيمة عددًا صحيحًا بين :min و:max.',
    en: 'The value must be a whole number between :min and :max.',
  },
  choice: { ar: 'القيمة المختارة غير صالحة.', en: 'The selected value is invalid.' },
}
