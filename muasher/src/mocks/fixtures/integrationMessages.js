export const integrationMessages = {
  unauthenticated: { ar: 'يرجى تسجيل الدخول للمتابعة.', en: 'Please sign in to continue.' },
  forbidden: { ar: 'ليست لديك صلاحية لتنفيذ هذا الإجراء.', en: 'You do not have permission to perform this action.' },
  not_found: { ar: 'المورد المطلوب غير موجود.', en: 'The requested resource was not found.' },
  validation_failed: { ar: 'البيانات المرسلة غير صالحة.', en: 'The submitted data is invalid.' },
  reason_required: { ar: 'يجب كتابة سبب لهذا الإجراء.', en: 'A reason is required for this action.' },
  rate_limited: { ar: 'طلبات كثيرة. يرجى المحاولة لاحقًا.', en: 'Too many requests. Please try again later.' },
  provider_error: {
    ar: 'رفض مزوّد الخدمة الطلب أو تعذّر الوصول إليه. راجع الإعدادات ثم أعد المحاولة.',
    en: 'The provider rejected the request or could not be reached. Review the settings and try again.',
  },
  'integration.not_configured': {
    ar: 'لم يتم إعداد هذا التكامل بعد. احفظ بيانات الربط أولًا.',
    en: 'This integration is not configured yet. Save its credentials first.',
  },
  'integration.test_required': {
    ar: 'نفّذ اختبار اتصال ناجحًا لهذه البيئة قبل تفعيلها.',
    en: 'Run a successful connection test of this environment before activating it.',
  },
  'integration.configuration_incomplete': {
    ar: 'أكمل الحقول المطلوبة لهذا المزوّد قبل تفعيله.',
    en: 'Complete the required fields of this provider before activating it.',
  },
  'ai.data_transfer_acknowledgement_required': {
    ar: 'يلزم الإقرار بأن أسئلة الزوار ومقتطفات قاعدة المعرفة تُرسل إلى مزوّد خارج المملكة قبل التفعيل.',
    en: 'Acknowledge that visitor questions and knowledge-base excerpts are sent to a provider outside the Kingdom before activating it.',
  },
  'payment.method_requires_confirmation': {
    ar: 'لا يمكن تفعيل Apple Pay أو STC Pay إلا بعد تأكيد خطوات التفعيل الخاصة بهما.',
    en: 'Apple Pay and STC Pay can be enabled only after their activation steps are confirmed.',
  },
  'payment.confirmation_in_use': {
    ar: 'لا يمكن سحب هذا التأكيد ما دامت وسيلة الدفع المرتبطة به مفعّلة.',
    en: 'This confirmation cannot be withdrawn while the payment method that depends on it is enabled.',
  },
  'payment.webhook_requires_https': {
    ar: 'يتطلب التسجيل التلقائي للإشعارات أن تعمل المنصة على عنوان HTTPS.',
    en: 'Automatic webhook registration needs the platform to run on an HTTPS address.',
  },
  'payment.apple_pay_file_missing': {
    ar: 'لم يتم رفع ملف التحقق من النطاق لـ Apple Pay.',
    en: 'No Apple Pay domain association file has been uploaded.',
  },
  'sms.recipient_not_in_test_numbers': {
    ar: 'في وضع الاختبار يمكن الإرسال إلى أرقام الاختبار المسموح بها فقط.',
    en: 'In test mode, messages can be sent only to the allowed test numbers.',
  },
  'sms.template_not_approved': {
    ar: 'لا يطابق هذا النص أي قالب معتمد للمرسل في مسجات.',
    en: 'This text does not match any approved Msegat template of the sender.',
  },
  'sms.templates_not_loaded': {
    ar: 'حمّل قوالب مسجات المعتمدة قبل حفظ قوالب الرسائل.',
    en: 'Load the approved Msegat templates before saving the message templates.',
  },
  'email.host_not_allowed': {
    ar: 'عنوان خادم البريد هذا غير مسموح به. استخدم الاسم العام لخادم البريد.',
    en: 'This mail server address is not allowed. Use the public host name of the mail server.',
  },
  'email.test_address_required': {
    ar: 'أدخل بريد الاختبار قبل تحويل البريد إلى وضع الاختبار.',
    en: 'Enter a test mailbox before switching e-mail to test mode.',
  },
  'field.required': { ar: 'هذا الحقل مطلوب.', en: 'This field is required.' },
  'field.invalid': { ar: 'القيمة غير صالحة.', en: 'The value is not valid.' },
  'field.prefix': { ar: 'يجب أن تبدأ القيمة بـ :prefix', en: 'The value must start with :prefix.' },
  'field.length': { ar: 'يجب أن يكون الطول بين :min و :max حرفًا.', en: 'The length must be between :min and :max characters.' },
  'field.saudi_mobile': {
    ar: 'استخدم رقم جوال سعوديًا بالصيغة الدولية مثل +9665XXXXXXXX.',
    en: 'Use a Saudi mobile number in the international format +9665XXXXXXXX.',
  },
  'field.reason_min': { ar: 'يجب ألا يقل السبب عن :min أحرف.', en: 'The reason must be at least :min characters.' },
  'field.file_type': { ar: 'يجب أن يكون الملف نصيًا عاديًا.', en: 'The file must be a plain text file.' },
  'field.file_size': { ar: 'يجب ألا يتجاوز حجم الملف :max كيلوبايت.', en: 'The file must not be larger than :max kilobytes.' },
  'sms_template.unknown_placeholder': {
    ar: 'يستخدم النص متغيرًا غير مسموح به لهذه الرسالة.',
    en: 'The text uses a placeholder that is not allowed for this message.',
  },
  'sms_template.missing_placeholder': {
    ar: 'يجب أن يتضمن النص المتغيرات المطلوبة لهذه الرسالة.',
    en: 'The text must contain the required placeholders of this message.',
  },
  'sms_template.too_long': {
    ar: 'يجب أن يتسع النص في رسالة واحدة (70 حرفًا عربيًا أو 160 حرفًا لاتينيًا).',
    en: 'The text must fit in one SMS (70 Arabic characters or 160 Latin characters).',
  },
}
