export const NOTIFICATION_TEXT_FIXTURES = Object.freeze({
  'REG-01': [
    {
      key: 'entities.REG-01.entity',
      variant: 'entity',
      parts: {
        title: { ar: 'استلمنا تسجيل :entity', en: 'We received the registration of :entity' },
        body: {
          ar: 'سجّلت :entity في منصة مؤشر برقم :reference، وسيراجع فريق المنصة الطلب قريبًا.',
          en: ':entity registered on Moashir under :reference; the platform team will review it soon.',
        },
      },
    },
    {
      key: 'entities.REG-01.staff',
      variant: 'staff',
      parts: {
        title: { ar: 'تسجيل جهة جديدة: :entity', en: 'New entity registration: :entity' },
        body: {
          ar: 'سجّلت :entity برقم :reference وتنتظر المراجعة.',
          en: ':entity registered under :reference and is awaiting review.',
        },
      },
    },
  ],
  'USR-01': [
    {
      key: 'staff_invited',
      variant: 'staff_invited',
      parts: {
        subject: {
          ar: 'دعوة للانضمام إلى فريق المنصة',
          en: 'Invitation to join the platform team',
        },
        line: {
          ar: 'أنشأ مدير النظام حسابًا لك في منصة مؤشر.',
          en: 'The System Administrator created an account for you on Moashir.',
        },
        action: { ar: 'قبول الدعوة', en: 'Accept the invitation' },
        expiry: {
          ar: 'هذه الدعوة صالحة لمدة :duration وتُستخدم مرة واحدة.',
          en: 'This invitation is valid for :duration and can be used once.',
        },
      },
    },
  ],
})
