export const SANDBOX_OUTCOME_ORDER = [
  'paid',
  'declined',
  'insufficient_funds',
  'three_ds_completes',
  'three_ds_pending',
]

export const moyasarSandboxTexts = {
  ar: {
    notice: 'نموذج دفع تجريبي محلي يحاكي نموذج ميسر. لا تُدخل أي بيانات بطاقة ولا يُخصم أي مبلغ.',
    outcomeLabel: 'نتيجة الدفع التجريبية',
    methodLabel: 'وسيلة الدفع',
    pay: 'ادفع',
    processing: 'جارٍ تنفيذ الدفع التجريبي',
    failed: 'تعذّر إتمام الدفع التجريبي. أعد المحاولة.',
    interrupted: 'تعذّر تأكيد الدفع مع المنصة. أعد المحاولة.',
    outcomes: {
      paid: 'دفع ناجح',
      declined: 'رفض البطاقة',
      insufficient_funds: 'رصيد غير كافٍ',
      three_ds_completes: 'تحقق 3-D Secure يكتمل بعد لحظات',
      three_ds_pending: 'تحقق 3-D Secure لم يكتمل',
    },
    methods: {
      creditcard: 'بطاقة مدى أو بطاقة ائتمانية',
      applepay: 'Apple Pay',
      stcpay: 'STC Pay',
    },
  },
  en: {
    notice:
      'A local sandbox form that simulates the Moyasar form. Enter no card details; nothing is charged.',
    outcomeLabel: 'Sandbox payment outcome',
    methodLabel: 'Payment method',
    pay: 'Pay',
    processing: 'Processing the sandbox payment',
    failed: 'The sandbox payment could not be completed. Try again.',
    interrupted: 'The payment could not be confirmed with the platform. Try again.',
    outcomes: {
      paid: 'Successful payment',
      declined: 'Card declined',
      insufficient_funds: 'Insufficient funds',
      three_ds_completes: '3-D Secure completes after a moment',
      three_ds_pending: '3-D Secure not completed',
    },
    methods: { creditcard: 'mada or credit card', applepay: 'Apple Pay', stcpay: 'STC Pay' },
  },
}
