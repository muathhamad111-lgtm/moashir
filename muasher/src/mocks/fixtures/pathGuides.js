export const PATH_GUIDES_FIXTURE = {
  accredited: {
    path: 'accredited',
    pathLabel: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
    entityTexts: {
      intro: {
        ar: 'يقيّم مقيّم معتمد من منصة مؤشر شواهد جهتك لكل معيار فرعي، وتصدر الشهادة بعد اعتماد النتيجة.',
        en: 'A Moashir accredited assessor reviews your evidence for every sub-criterion, and the certificate is issued once the result is approved.',
      },
      instructions: {
        ar: 'ارفع الشواهد المطلوبة لكل معيار فرعي قبل الإرسال.\nاكتب وصفاً مختصراً للممارسة عندما يطلبه المعيار.\nتأكد أن كل ملف مقروء وحديث وخالٍ من البيانات الشخصية غير اللازمة.\nأرسل الطلب إلى المقيّم بعد اكتمال جميع المعايير الفرعية.',
        en: 'Upload the required evidence for every sub-criterion before you submit.\nWrite a short description of the practice when the criterion asks for it.\nMake sure every file is readable, recent and free of unnecessary personal data.\nSend the request to the assessor once every sub-criterion is complete.',
      },
      afterSubmission: {
        ar: 'يبدأ المقيّم المراجعة خلال المدة المحددة، وقد يطلب استكمال بعض الشواهد قبل اعتماد النتيجة.',
        en: 'The assessor starts the review within the set period and may ask you to complete some evidence before the result is approved.',
      },
    },
    reviewSlaDays: 14,
    uploadLimits: {
      maxFileMb: 20,
      allowedTypes: ['pdf', 'docx', 'xlsx', 'jpg', 'png'],
      maxFilesPerSub: 5,
    },
    isPaid: true,
    price: null,
  },
  advanced: {
    path: 'advanced',
    pathLabel: { ar: 'التقييم المتقدم (الجائزة)', en: 'Advanced assessment (Award)' },
    entityTexts: {
      intro: {
        ar: 'يُفتح التقييم المتقدم في دورات الجائزة، ويمر بالتصفية ثم تقييم لجنة المقيّمين.',
        en: 'The advanced assessment opens in award cycles and goes through filtering and then the assessors committee.',
      },
      instructions: null,
      afterSubmission: null,
    },
    reviewSlaDays: 30,
    uploadLimits: { maxFileMb: 20, allowedTypes: ['pdf', 'docx', 'xlsx'], maxFilesPerSub: 5 },
    isPaid: false,
    price: null,
  },
}
