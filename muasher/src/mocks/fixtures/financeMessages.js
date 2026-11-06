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
    ar: 'لدى جهتك طلب تقييم معتمد مفتوح. أكمله قبل سداد رسوم طلب جديد.',
    en: 'Your entity already has an open accredited assessment request. Complete it before paying for a new one.',
  },
  'accredited.payment_being_processed': {
    ar: 'تجري معالجة سداد رسوم التقييم المعتمد، وسيُفتح طلبك خلال وقت قصير.',
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
  'payment.channel_not_available': {
    ar: 'طريقة الدفع هذه غير متاحة حاليًا. اختر طريقة دفع أخرى.',
    en: 'This payment method is not available right now. Choose another payment method.',
  },
  'payment.channel_not_supported': {
    ar: 'لا ينطبق هذا الإجراء على طلبات الدفع بالتحويل البنكي.',
    en: 'This action does not apply to bank-transfer payment requests.',
  },
  'payment.open_order_exists': {
    ar: 'لديك طلب دفع مفتوح لهذه الخدمة. أكمل الطلب الحالي أو انتظر نتيجة مراجعته.',
    en: 'You already have an open payment request for this service. Complete it or wait for its review.',
  },
  'payment.gateway_not_ready': {
    ar: 'لا يمكن تفعيل الدفع الإلكتروني قبل إعداد بوابة الدفع وتفعيلها.',
    en: 'Electronic payment cannot be enabled before the payment gateway is configured and activated.',
  },
  'payment.bank_account_required': {
    ar: 'أضف حسابًا بنكيًا نشطًا واحدًا على الأقل قبل تفعيل التحويل البنكي.',
    en: 'Add at least one active bank account before enabling bank transfer.',
  },
  'bank_account.not_selectable': {
    ar: 'هذا الحساب البنكي لا يستقبل تحويلات. اختر حسابًا آخر من القائمة.',
    en: 'This bank account does not accept transfers. Choose another account from the list.',
  },
  'bank_account.pending_transfers': {
    ar: 'توجد حوالات بانتظار الاعتماد على هذا الحساب. اعتمدها أو ارفضها أولًا.',
    en: 'Transfers awaiting approval use this account. Approve or reject them first.',
  },
  'bank_account.last_active_account': {
    ar: 'هذا آخر حساب بنكي نشط والتحويل البنكي مفعّل. أضف حسابًا آخر أو أوقف التحويل البنكي أولًا.',
    en: 'This is the last active bank account and bank transfer is enabled. Add another account or turn bank transfer off first.',
  },
  'bank_account.limit_reached': {
    ar: 'وصلت إلى الحد الأعلى لعدد الحسابات البنكية النشطة. أوقف حسابًا قبل إضافة حساب آخر أو تفعيله.',
    en: 'The limit of active bank accounts is reached. Deactivate an account before adding or activating another.',
  },
  'bank_account.payee_fields_immutable': {
    ar: 'لا يمكن تعديل بيانات المستفيد بعد الحفظ. أضف حسابًا جديدًا ثم أوقف الحساب القديم أو احذفه.',
    en: 'Payee details cannot change after saving. Add a new account, then deactivate or delete the old one.',
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
  vat_registration_number_format: {
    ar: 'أدخل 15 رقماً يبدأ وينتهي بالرقم 3، بالأرقام اللاتينية ودون مسافات.',
    en: 'Enter 15 Western digits that start and end with 3, without spaces.',
  },
  between: {
    ar: 'يجب أن تكون القيمة عددًا صحيحًا بين :min و:max.',
    en: 'The value must be a whole number between :min and :max.',
  },
  choice: { ar: 'القيمة المختارة غير صالحة.', en: 'The selected value is invalid.' },
  iban_format: {
    ar: 'صيغة رقم الآيبان غير صحيحة: SA ثم رقمان للتحقق ورقمان لرمز البنك و18 خانة.',
    en: 'The IBAN format is not valid: SA, 2 check digits, a 2-digit bank code and 18 characters.',
  },
  iban_country: {
    ar: 'يقبل هذا الحقل رقم آيبان سعوديًا فقط يبدأ بـ SA.',
    en: 'This field accepts a Saudi IBAN only, starting with SA.',
  },
  iban_length: {
    ar: 'رقم الآيبان السعودي 24 خانة.',
    en: 'A Saudi IBAN has 24 characters.',
  },
  iban_checksum: {
    ar: 'خانتا التحقق لا تطابقان الرقم. راجع الرقم كما في خطاب البنك.',
    en: 'The check digits do not match the number. Check it against the bank letter.',
  },
  iban_taken: {
    ar: 'رقم الآيبان هذا مسجّل في حساب آخر.',
    en: 'This IBAN is already registered on another account.',
  },
  rejection_note_required: {
    ar: 'اكتب ملاحظة للجهة عند اختيار «سبب آخر».',
    en: 'Write a note for the entity when you choose "Other".',
  },
}
