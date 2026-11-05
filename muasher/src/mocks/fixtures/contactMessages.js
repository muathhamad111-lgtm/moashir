export const CONTACT_MOCK_MESSAGES = {
  unauthenticated: { ar: 'يجب تسجيل الدخول أولاً.', en: 'You must sign in first.' },
  validation_failed: {
    ar: 'البيانات المرسلة غير صالحة.',
    en: 'The submitted data is invalid.',
  },
  rate_limited: {
    ar: 'طلبات كثيرة. يرجى المحاولة لاحقًا.',
    en: 'Too many requests. Please try again later.',
  },
  not_found: { ar: 'لم يُعثر على الرسالة.', en: 'The message was not found.' },
  'contact.message_closed': {
    ar: 'أُغلق هذا الاستفسار ولا يمكن الرد عليه.',
    en: 'This enquiry is closed and cannot be answered.',
  },
}

export const CONTACT_MOCK_FIELD_MESSAGES = {
  required: { ar: 'هذا الحقل مطلوب.', en: 'This field is required.' },
  email: { ar: 'أدخل بريدًا إلكترونيًا صحيحًا.', en: 'Enter a valid e-mail address.' },
  min: { ar: 'يجب ألا يقل عن :min أحرف.', en: 'Must be at least :min characters.' },
  max: { ar: 'يجب ألا يزيد على :max حرفًا.', en: 'Must not exceed :max characters.' },
  mobile: {
    ar: 'أدخل رقم جوال بالصيغة 05XXXXXXXX أو +9665XXXXXXXX.',
    en: 'Enter a mobile number as 05XXXXXXXX or +9665XXXXXXXX.',
  },
  enquiry: { ar: 'اختر نوع الاستفسار.', en: 'Choose the enquiry type.' },
  consent: { ar: 'يجب الموافقة على سياسة الخصوصية.', en: 'You must accept the privacy policy.' },
  status: { ar: 'حالة غير معروفة.', en: 'Unknown status.' },
}

export const SENDER_THREADS_FIXTURE = [
  {
    id: '7c2f4e1a-52b0-4c8e-9a31-0000000000a1',
    reference: 'CNT-2026-000052',
    status: 'replied',
    enquiryType: 'accredited_assessment',
    createdMinutesAgo: 2 * 24 * 60,
    repliedMinutesAgo: 120,
    closedMinutesAgo: null,
    hasUnreadReply: true,
    entries: [
      {
        author: 'sender',
        minutesAgo: 2 * 24 * 60,
        body: 'السلام عليكم، هل يمكن رفع أكثر من ملف لشاهد واحد في المعيار الفرعي 3.1 «تخطيط القوى العاملة والتطوع»؟ لدينا الخطة وملحقها في ملفين منفصلين.',
      },
      {
        author: 'team',
        minutesAgo: 120,
        body: 'شكراً لتواصلكم، يمكنكم رفع الشواهد بصيغة PDF بحد أقصى عشرين ميجابايت لكل ملف.',
      },
    ],
  },
  {
    id: '7c2f4e1a-52b0-4c8e-9a31-0000000000a3',
    reference: 'CNT-2026-000047',
    status: 'in_progress',
    enquiryType: 'payments',
    createdMinutesAgo: 3 * 24 * 60,
    repliedMinutesAgo: null,
    closedMinutesAgo: null,
    hasUnreadReply: false,
    entries: [
      {
        author: 'sender',
        minutesAgo: 3 * 24 * 60,
        body: 'نرجو تعديل اسم الجهة في إيصال الدفع ليطابق السجل الرسمي للجهة.',
      },
    ],
  },
  {
    id: '7c2f4e1a-52b0-4c8e-9a31-0000000000a2',
    reference: 'CNT-2026-000031',
    status: 'closed',
    enquiryType: 'accredited_assessment',
    createdMinutesAgo: 21 * 24 * 60,
    repliedMinutesAgo: 20 * 24 * 60 + 30,
    closedMinutesAgo: 20 * 24 * 60,
    hasUnreadReply: false,
    entries: [
      {
        author: 'sender',
        minutesAgo: 21 * 24 * 60,
        body: 'متى تبدأ دورة التقييم المعتمد لعام 2026؟ وهل يمكن تجهيز الشواهد قبل الدفع؟',
      },
      {
        author: 'team',
        minutesAgo: 20 * 24 * 60 + 60,
        body: 'يُفتح نموذج رفع الشواهد مباشرة بعد إتمام الدفع، ويمكنكم تجهيز الملفات مسبقاً حسب دليل كل معيار.',
      },
      {
        author: 'sender',
        minutesAgo: 20 * 24 * 60 + 30,
        body: 'شكراً لكم، اتضحت الصورة.',
      },
    ],
  },
]
