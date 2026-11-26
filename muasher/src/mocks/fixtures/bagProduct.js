const PRICE_BEFORE_TAX = 2500
const VAT_RATE = 0.15

export function computePricing(priceBeforeTax = PRICE_BEFORE_TAX, vatRate = VAT_RATE) {
  const taxAmount = Math.round(priceBeforeTax * vatRate)
  return {
    priceBeforeTax,
    vatRate,
    taxAmount,
    total: priceBeforeTax + taxAmount,
    currency: 'SAR',
    effectiveFrom: '2026-01-01T00:00:00+03:00',
  }
}

export function seedBagProduct() {
  return {
    availability: 'available',
    repurchasePolicy: 'after-completed',
    pricing: computePricing(),
    marketing: {
      title: { ar: 'حقيبة التميز السريعة', en: 'Quick Excellence Kit' },
      tagline: {
        ar: 'رحلة تحسين عملية مبنية على نتيجتك المعتمدة — لا تقييم جديد.',
        en: 'A practical improvement journey built on your accredited result — not a new assessment.',
      },
      whatIsIt: {
        ar: 'حقيبة التميز السريع رحلة تطوير مؤسسي تحوّل فجوات تقييمك المعتمد إلى مراحل تحسين عملية بإرشاد مقيّم مختص. هي ليست مسار تقييم جديدًا ولا تمنح درجة جديدة.',
        en: 'the Quick Excellence Kit is an institutional-development journey that turns your accredited-assessment gaps into practical improvement stages guided by a specialized assessor. It is not a new assessment path and never grants a new score.',
      },
      whyNeed: {
        ar: 'لتحويل الفجوات إلى أولويات ومراحل وأدوات عملية قابلة للمتابعة، بدل قائمة توصيات عامة.',
        en: 'To turn gaps into priorities, stages, and practical tools you can act on — instead of a generic list of recommendations.',
      },
      howItWorks: [
        { ar: 'نتيجة معتمدة', en: 'Accredited result' },
        { ar: 'شراء الخدمة', en: 'Purchase the service' },
        { ar: 'إسناد مقيّم', en: 'Assessor assignment' },
        { ar: 'تحليل الفجوات', en: 'Gap analysis' },
        { ar: 'مراحل تحسين', en: 'Improvement stages' },
        { ar: 'متابعة النتائج', en: 'Track results' },
      ],
      outputs: [
        {
          ar: 'خريطة فجوات مرتبطة بمعايير التقييم',
          en: 'A gap map tied to the assessment criteria',
        },
        { ar: 'خطة مراحل بأولويات واضحة', en: 'A staged plan with clear priorities' },
        {
          ar: 'نماذج وأدلة وقوائم تحقق مساندة',
          en: 'Supporting templates, guides, and checklists',
        },
        {
          ar: 'سجل تقدم ومقارنة قبل/بعد عند إعادة التقييم',
          en: 'A progress log and a before/after comparison at re-assessment',
        },
      ],
      eligibilityNote: {
        ar: 'يشترط وجود نتيجة تقييم معتمد مكتملة لبدء بناء الحقيبة. التقييم الذاتي والمتقدم لا يُستخدمان كمرجع.',
        en: 'A completed accredited-assessment result is required to build the Kit. Self-assessment and advanced results are not used as the reference.',
      },
      faqs: [
        {
          q: { ar: 'هل الحقيبة تقييم جديد؟', en: 'Is the Kit a new assessment?' },
          a: {
            ar: 'لا. الحقيبة رحلة تحسين لا تمنح درجة؛ يُقاس التحسن فقط عند عودتك لتقييم معتمد جديد.',
            en: 'No. The Kit is an improvement journey that grants no score; improvement is measured only when you return to a new accredited assessment.',
          },
        },
        {
          q: { ar: 'على أي نتيجة تُبنى الحقيبة؟', en: 'Which result is the Kit built on?' },
          a: {
            ar: 'على نتيجة تقييمك المعتمد فقط. تظهر نتائج التقييم الذاتي والمتقدم للمقيّم للاطلاع فقط.',
            en: 'On your accredited result only. Self-assessment and advanced results are shown to the assessor for context only.',
          },
        },
        {
          q: { ar: 'هل يمكن شراء حقيبة جديدة لاحقًا؟', en: 'Can I buy a new Kit later?' },
          a: {
            ar: 'نعم، وفق سياسة إعادة الشراء المعتمدة — عادةً بعد اكتمال الحقيبة الحالية أو بصدور تقييم معتمد جديد.',
            en: 'Yes, per the approved re-purchase policy — typically after the current Kit completes or when a new accredited result is issued.',
          },
        },
      ],
    },
    messages: {
      paySuccess: {
        ar: 'تم تفعيل اشتراك حقيبة التميز السريع، وطلبك الآن بانتظار إسناد مقيّم.',
        en: 'Your Quick Excellence Kit subscription is active; your request is now awaiting assessor assignment.',
      },
      payFailed: {
        ar: 'لم تكتمل عملية الدفع. يمكنك إعادة المحاولة دون إنشاء طلب مكرر.',
        en: 'The payment did not complete. You can retry without creating a duplicate request.',
      },
    },
  }
}
