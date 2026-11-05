export const authMessages = {
  validation_failed: { ar: 'البيانات المرسلة غير صالحة.', en: 'The submitted data is invalid.' },
  unauthenticated: { ar: 'يرجى تسجيل الدخول للمتابعة.', en: 'Please sign in to continue.' },
  forbidden: {
    ar: 'ليست لديك صلاحية لتنفيذ هذا الإجراء.',
    en: 'You do not have permission to perform this action.',
  },
  rate_limited: {
    ar: 'طلبات كثيرة. يرجى المحاولة لاحقًا.',
    en: 'Too many requests. Please try again later.',
  },
  reason_required: {
    ar: 'يجب كتابة سبب لهذا الإجراء.',
    en: 'A reason is required for this action.',
  },
  account_locked: {
    ar: 'الحساب مقفل مؤقتًا. يرجى المحاولة لاحقًا.',
    en: 'The account is temporarily locked. Please try again later.',
  },
  account_deactivated: { ar: 'هذا الحساب غير نشط.', en: 'This account is not active.' },
  'credentials.invalid': {
    ar: 'بيانات الدخول غير صحيحة.',
    en: 'The sign-in details are incorrect.',
  },
  'otp.invalid': { ar: 'رمز التحقق غير صحيح.', en: 'The verification code is incorrect.' },
  'otp.expired': {
    ar: 'انتهت صلاحية رمز التحقق. يرجى طلب رمز جديد.',
    en: 'The verification code has expired. Please request a new one.',
  },
  'otp.challenge_invalid': {
    ar: 'طلب التحقق هذا لم يعد صالحًا. يرجى طلب رمز جديد.',
    en: 'This verification request is no longer valid. Please request a new code.',
  },
  'otp.attempts_exceeded': {
    ar: 'محاولات خاطئة كثيرة. يرجى طلب رمز جديد.',
    en: 'Too many incorrect attempts. Please request a new code.',
  },
  'otp.cooldown': {
    ar: 'يرجى الانتظار قبل طلب رمز جديد.',
    en: 'Please wait before requesting a new code.',
  },
  'mfa.invalid': { ar: 'رمز المصادقة غير صحيح.', en: 'The authentication code is incorrect.' },
  'phone.missing': {
    ar: 'أضف رقم جوال إلى حسابك قبل التحقق منه.',
    en: 'Add a mobile number to your account before verifying it.',
  },
  'phone.already_verified': {
    ar: 'رقم الجوال هذا موثّق مسبقًا.',
    en: 'This mobile number is already verified.',
  },
  'phone.sms_unavailable': {
    ar: 'يتعذّر إرسال الرسائل النصية حاليًا. يرجى المحاولة لاحقًا.',
    en: 'Text messages cannot be sent right now. Please try again later.',
  },
  'phone.in_use': {
    ar: 'رقم الجوال هذا موثّق في حساب آخر. تواصل مع فريق المنصة.',
    en: 'This mobile number is already verified on another account. Contact the platform team.',
  },
  'field.required': { ar: 'هذا الحقل مطلوب.', en: 'This field is required.' },
  'field.identifier': {
    ar: 'أدخل بريدك الإلكتروني الرسمي، أو رقم جوالك السعودي (05XXXXXXXX)، أو الرقم الوطني الموحد للجهة (10 أرقام تبدأ بالرقم 7).',
    en: "Enter your official e-mail, your Saudi mobile number (05XXXXXXXX) or your entity's unified national number (10 digits starting with 7).",
  },
  'field.invalid': { ar: 'القيمة المدخلة غير صالحة.', en: 'The value is not valid.' },
  'channels.email_required': {
    ar: 'يجب أن يبقى البريد الإلكتروني مسموحًا به، فهو القناة التي تضمن وصول رمز الدخول إلى كل جهة.',
    en: 'E-mail must stay allowed: every entity can always receive its sign-in code by e-mail.',
  },
  'channels.sms_unavailable': {
    ar: 'اضبط بوابة الرسائل النصية أولًا قبل السماح بإرسال رمز الدخول برسالة نصية.',
    en: 'Configure the SMS gateway before allowing sign-in codes by text message.',
  },
  'field.reason_min': {
    ar: 'يجب ألا يقل السبب عن :min أحرف.',
    en: 'The reason must be at least :min characters.',
  },
}

export const authNotices = {
  signInCodeSent: {
    ar: 'إذا كان ما أدخلته يطابق حساب جهة مسجّلًا، فقد أرسلنا إليك رمز الدخول.',
    en: 'If an entity account matches what you entered, a sign-in code has been sent.',
  },
}
