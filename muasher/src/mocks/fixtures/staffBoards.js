const PATH_LABELS = {
  self: { ar: 'التقييم الذاتي', en: 'Self assessment' },
  accredited: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  advanced: { ar: 'التقييم المتقدم (الجائزة)', en: 'Advanced assessment (Award)' },
}

const REGIONS = [
  { regionId: 1, code: 'RYD', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' }, count: 18 },
  { regionId: 2, code: 'MKK', name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' }, count: 11 },
  { regionId: 5, code: 'EST', name: { ar: 'المنطقة الشرقية', en: 'Eastern Province' }, count: 9 },
  {
    regionId: 3,
    code: 'MDN',
    name: { ar: 'منطقة المدينة المنورة', en: 'Madinah Region' },
    count: 4,
  },
]

const LOWEST_CRITERIA = [
  {
    code: '4',
    name: { ar: 'إدارة الموارد', en: 'Resource management' },
    averagePercent: '58.40',
    results: 6,
  },
  {
    code: '7',
    name: { ar: 'قياس الأثر', en: 'Impact measurement' },
    averagePercent: '61.25',
    results: 6,
  },
  {
    code: '2',
    name: { ar: 'التخطيط الاستراتيجي', en: 'Strategic planning' },
    averagePercent: '66.10',
    results: 6,
  },
]

function card(key, value, { format = 'count', link = null } = {}) {
  return { key, value, format, link }
}

function path(value) {
  return { path: value, pathLabel: PATH_LABELS[value] }
}

const MEMBER_CYCLES = [
  {
    cycleId: 3,
    name: { ar: 'دورة جائزة التميز 2026', en: 'Excellence Award cycle 2026' },
    status: 'committee-assessment',
    submissionEnd: '2026-09-30T20:59:00+00:00',
    committeeStart: '2026-10-15T21:00:00+00:00',
    committeeEnd: '2026-11-30T20:59:00+00:00',
    requests: 12,
  },
]

export const STAFF_BOARDS = {
  system_admin: {
    cards: [
      card('entities_total', 42, { link: '/admin/entities' }),
      card('entities_active', 37, { link: '/admin/entities' }),
      card('entities_pending_verification', 3, { link: '/admin/entities' }),
      card('staff_users_active', 14, { link: '/admin/settings/users' }),
      card('accredited_requests', 9, { link: '/admin/requests' }),
      card('open_requests', 6, { link: '/admin/requests' }),
      card('overdue_requests', 1, { link: '/admin/requests?overdue=true' }),
      card('payments_collected', '10350.00', { format: 'money', link: '/finance' }),
    ],
    widgets: [
      { key: 'entities_by_region', data: REGIONS },
      {
        key: 'entities_by_sector',
        data: [
          { key: 'social', count: 16 },
          { key: 'educational', count: 11 },
          { key: 'health', count: 8 },
          { key: 'developmental', count: 7 },
        ],
      },
      { key: 'lowest_criteria', data: LOWEST_CRITERIA },
      {
        key: 'activity',
        data: [
          {
            assessmentId: 14,
            number: 'ACC-2026-000014',
            ...path('accredited'),
            verb: 'submit',
            toStatus: 'submitted',
            at: '2026-11-04T07:20:00+00:00',
          },
          {
            assessmentId: 12,
            number: 'ACC-2026-000012',
            ...path('accredited'),
            verb: 'complete',
            toStatus: 'completed',
            at: '2026-11-03T12:05:00+00:00',
          },
        ],
      },
    ],
  },
  executive: {
    cards: [
      card('entities_total', 42),
      card('accredited_assessments_completed', 6),
      card('accredited_average_percent', '74.30', { format: 'percent' }),
      card('payments_collected', '10350.00', { format: 'money' }),
    ],
    widgets: [
      { key: 'entities_by_region', data: REGIONS },
      { key: 'lowest_criteria', data: LOWEST_CRITERIA },
    ],
  },
  request_follower: {
    cards: [
      card('awaiting_assignment', 3, {
        link: '/admin/requests?status=submitted%2Cawaiting_review_assignment',
      }),
      card('assigned', 4, { link: '/admin/requests' }),
      card('in_review', 2, { link: '/admin/requests' }),
      card('needs_completion', 1, { link: '/admin/requests' }),
      card('completed', 6, { link: '/admin/requests' }),
      card('overdue', 1, { link: '/admin/requests?overdue=true' }),
      card('completion_rate', '40.00', { format: 'percent' }),
    ],
    widgets: [
      { key: 'requests_by_path', data: [{ ...path('accredited'), count: 9 }] },
      {
        key: 'pipeline',
        data: [
          { ...path('accredited'), status: 'submitted', count: 3 },
          { ...path('accredited'), status: 'under-review', count: 2 },
        ],
      },
      {
        key: 'assessors',
        data: [
          { assessorId: 10, name: 'د. خالد بن سعد القحطاني', active: 3, completed: 5 },
          { assessorId: 11, name: 'أ. منيرة بنت علي الشهري', active: 1, completed: 2 },
        ],
      },
    ],
  },
  assessor: {
    cards: [
      card('to_start', 1, { link: '/admin/requests/assigned' }),
      card('in_progress', 2, { link: '/admin/requests/assigned' }),
      card('awaiting_entity', 1, { link: '/admin/requests/assigned' }),
      card('overdue', 0, { link: '/admin/requests/assigned' }),
      card('completed', 5, { link: '/admin/requests/assigned' }),
      card('average_duration_days', '6.50', { format: 'days' }),
    ],
    widgets: [
      {
        key: 'due_soon',
        data: [
          {
            id: 14,
            number: 'ACC-2026-000014',
            ...path('accredited'),
            status: 'under-review',
            entityName: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
            dueAt: '2026-11-08T20:59:00+00:00',
            isOverdue: false,
          },
        ],
      },
      { key: 'weakest_criteria', data: LOWEST_CRITERIA.slice(0, 2) },
    ],
  },
  committee_assessor: {
    cards: [
      card('assigned', 6),
      card('completed', 2),
      card('remaining', 4),
      card('progress', '33.33', { format: 'percent' }),
      card('own_average', '78.50', { format: 'percent' }),
    ],
    widgets: [
      {
        key: 'cycles',
        data: [
          {
            cycleId: 3,
            name: MEMBER_CYCLES[0].name,
            status: 'committee-assessment',
            committeeStart: MEMBER_CYCLES[0].committeeStart,
            committeeEnd: MEMBER_CYCLES[0].committeeEnd,
            assigned: 6,
            completed: 2,
            remaining: 4,
          },
        ],
      },
      { key: 'latest_tasks', data: [] },
    ],
  },
  filtering_officer: {
    cards: [card('awaiting_filtering', 4), card('qualified', 7), card('not_qualified', 1)],
    widgets: [{ key: 'cycles', data: MEMBER_CYCLES }],
  },
  award_secretary: {
    cards: [
      card('applicants', 12),
      card('qualified', 9),
      card('in_committee', 9),
      card('committee_progress', '45.00', { format: 'percent' }),
    ],
    widgets: [
      { key: 'cycles', data: MEMBER_CYCLES },
      {
        key: 'score_histogram',
        data: [
          { key: 'from_90_to_100', count: 1 },
          { key: 'from_80_to_89', count: 3 },
          { key: 'from_70_to_79', count: 2 },
          { key: 'from_60_to_69', count: 1 },
          { key: 'below_60', count: 0 },
        ],
      },
    ],
  },
  finance_officer: {
    cards: [
      card('revenue', '9000.00', { format: 'money', link: '/finance' }),
      card('vat_collected', '1350.00', { format: 'money', link: '/finance' }),
      card('payments_paid', 9, { link: '/finance/transactions?status=paid' }),
      card('payments_open', 1, { link: '/finance/transactions' }),
      card('refunds_total', '1150.00', { format: 'money', link: '/finance/refunds' }),
      card('receipts_issued', 9),
    ],
    widgets: [
      {
        key: 'daily_payments',
        data: [
          { date: '2026-11-03', count: 2, total: '2300.00' },
          { date: '2026-11-04', count: 1, total: '1150.00' },
        ],
      },
      { key: 'by_service', data: [{ purpose: 'accredited', count: 9, total: '10350.00' }] },
    ],
  },
  content_officer: {
    cards: [
      card('pages_total', 7, { link: '/admin/settings/homepage' }),
      card('pages_published', 6, { link: '/admin/settings/homepage' }),
      card('pages_with_draft_changes', 1, { link: '/admin/settings/homepage' }),
      card('kpis_total', 8, { link: '/admin/kpis' }),
    ],
    widgets: [
      {
        key: 'latest_edits',
        data: [
          {
            type: 'page',
            id: 1,
            key: 'home',
            title: { ar: 'الصفحة الرئيسية', en: 'Home page' },
            updatedAt: '2026-11-04T09:30:00+00:00',
          },
        ],
      },
    ],
  },
  criteria_officer: {
    cards: [
      card('main_criteria', 9, { link: '/admin/settings/criteria' }),
      card('sub_criteria', 27, { link: '/admin/settings/criteria' }),
      card('guiding_points', 81),
      card('draft_problems', 0),
    ],
    widgets: [
      {
        key: 'published_version',
        data: {
          id: 2,
          versionNumber: 2,
          label: { ar: 'إصدار 2026', en: 'Version 2026' },
          scaleKind: 'points',
          publishedAt: '2026-09-01T09:00:00+00:00',
        },
      },
      { key: 'draft_version', data: null },
    ],
  },
}
