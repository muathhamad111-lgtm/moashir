export const criteriaErrorMessages = {
  unauthenticated: { ar: 'يجب تسجيل الدخول أولًا.', en: 'Unauthenticated.' },
  forbidden: { ar: 'لا تملك صلاحية تنفيذ هذا الإجراء.', en: 'This action is unauthorized.' },
  not_found: { ar: 'العنصر المطلوب غير موجود.', en: 'The requested item was not found.' },
  validation_failed: { ar: 'تحقق من البيانات المدخلة.', en: 'Check the data you entered.' },
  reason_required: {
    ar: 'يجب إدخال سبب لهذا الإجراء.',
    en: 'A reason is required for this action.',
  },
  'order.incomplete': {
    ar: 'يجب أن يتضمن الترتيب الجديد جميع عناصر القائمة مرة واحدة لكل عنصر.',
    en: 'The new order must list every item of the list exactly once.',
  },
  'criteria.no_draft': {
    ar: 'لا توجد مسودة لإصدار المعايير. أنشئ مسودة من الإصدار المنشور أولًا.',
    en: 'There is no draft criteria version. Create a draft from the published version first.',
  },
  'criteria.draft_exists': {
    ar: 'توجد مسودة لإصدار المعايير حاليًا. انشرها أو احذفها قبل إنشاء مسودة أخرى.',
    en: 'A draft criteria version already exists. Publish or discard it before creating another one.',
  },
  'criteria.version_not_draft': {
    ar: 'لا يمكن إجراء هذا التعديل إلا على مسودة إصدار المعايير. أنشئ مسودة جديدة لتعديل المعايير المنشورة.',
    en: 'Only a draft criteria version can be changed this way. Create a new draft to change published criteria.',
  },
  'criteria.version_is_draft': {
    ar: 'تُعدَّل المسودة مباشرة، وتُطبَّق التصحيحات النصية على الإصدارات المنشورة أو المؤرشفة فقط.',
    en: 'A draft version is edited directly. Text corrections apply to published or archived versions only.',
  },
  'criteria.no_published_version': {
    ar: 'لا يوجد إصدار منشور من المعايير.',
    en: 'No published criteria version is available.',
  },
  'criteria.clone_source_invalid': {
    ar: 'اختر إصدارًا منشورًا أو مؤرشفًا من المعايير لنسخه.',
    en: 'Choose a published or archived criteria version to copy.',
  },
  'criteria.weights_inconsistent': {
    ar: 'لا يمكن نشر المعايير قبل اتساق الأوزان.',
    en: 'The criteria cannot be published until the weights are consistent.',
  },
  'criteria.scope_required': {
    ar: 'اختر نطاق تطبيق التعديل: جميع التقييمات، أو الطلبات الجديدة فقط، أو إعادة تعيين الطلبات المفتوحة.',
    en: 'Choose where this change applies: all assessments, new requests only, or reset the open requests.',
  },
  'criteria.weight_exceeds_remaining': {
    ar: 'يتجاوز الوزن الوزن المتبقي البالغ :remaining.',
    en: 'The weight exceeds the remaining weight of :remaining.',
  },
  'criteria.weight_below_allocated': {
    ar: 'لا يمكن أن يقل الوزن عن :allocated الموزعة على عناصره.',
    en: 'The weight cannot be lower than the :allocated already allocated to its items.',
  },
  'criteria.weight_required': {
    ar: 'أدخل الوزن، أو فعّل التوزيع المتساوي في المعيار الأعلى.',
    en: 'Enter a weight, or turn on equal distribution in the parent criterion.',
  },
  'criteria.weight_too_small_to_distribute': {
    ar: 'لا يمكن توزيع الوزن :weight بالتساوي على :shares من العناصر.',
    en: 'The weight :weight cannot be distributed equally over :shares items.',
  },
  'criteria.sub_evaluated_directly': {
    ar: 'يُقيَّم هذا المعيار الفرعي مباشرة، لذا لا يمكن تقييم نقاط استرشادية عليه.',
    en: 'This sub-criterion is evaluated directly, so guiding points cannot be scored on it.',
  },
  'criteria.points_confirm_required': {
    ar: 'يؤدي التحويل إلى التقييم المباشر إلى حذف :count من النقاط الاسترشادية. أكّد للمتابعة.',
    en: 'Switching to direct evaluation deletes :count guiding points. Confirm to continue.',
  },
  'criteria.node_not_in_version': {
    ar: 'هذا العنصر لا ينتمي إلى إصدار المعايير المحدد.',
    en: 'This item does not belong to the selected criteria version.',
  },
  'criteria.text_required': {
    ar: 'أدخل النص باللغتين العربية والإنجليزية بحرفين على الأقل.',
    en: 'Enter the text in Arabic and English with at least 2 characters.',
  },
  'field.required': { ar: 'هذا الحقل مطلوب.', en: 'This field is required.' },
  'field.invalid': { ar: 'القيمة المدخلة غير صالحة.', en: 'The value is not valid.' },
  'field.prohibited': {
    ar: 'لا يمكن تعديل هذا الحقل هنا.',
    en: 'This field cannot be changed here.',
  },
  'field.weight': {
    ar: 'أدخل وزنًا أكبر من صفر وبمنزلتين عشريتين على الأكثر.',
    en: 'Enter a weight greater than zero with at most two decimals.',
  },
  'criteria.reason_length': {
    ar: 'يجب أن يتكون السبب من 10 أحرف على الأقل.',
    en: 'The reason must be at least 10 characters.',
  },
}

export const criteriaProblemMessages = {
  no_active_main: {
    ar: 'يجب أن يتضمن الإصدار معيارًا رئيسيًا مفعّلًا واحدًا على الأقل.',
    en: 'The version needs at least one active main criterion.',
  },
  weights_mismatch: {
    ar: 'مجموع أوزان المعايير الفرعية المفعّلة في :code يساوي :actual، ويجب أن يساوي وزن المعيار الرئيسي البالغ :expected.',
    en: 'The active sub-criteria of :code total :actual, but they must equal the main criterion weight of :expected.',
  },
  no_active_point: {
    ar: 'المعيار الفرعي :code يُقيَّم على النقاط الاسترشادية ولا توجد فيه نقطة استرشادية مفعّلة.',
    en: 'The sub-criterion :code is evaluated on guiding points but has no active guiding point.',
  },
  points_exceed_sub: {
    ar: 'مجموع أوزان النقاط الاسترشادية المفعّلة في :code يساوي :actual، وهو يتجاوز وزن المعيار الفرعي البالغ :expected.',
    en: 'The active guiding points of :code total :actual, which exceeds the sub-criterion weight of :expected.',
  },
}

export const criteriaNoticeTexts = {
  'STD-01': {
    title: { ar: 'معيار رئيسي جديد', en: 'New main criterion' },
    body: {
      ar: 'تمت إضافة المعيار الرئيسي :name_ar إلى مسودة المعايير.',
      en: 'The main criterion :name_en was added to the draft criteria.',
    },
  },
  'STD-02': {
    title: { ar: 'تعديل وزن معيار رئيسي', en: 'Main criterion weight changed' },
    body: {
      ar: 'تغيّر وزن :name_ar من :previous إلى :weight. راجع توزيع معاييره الفرعية.',
      en: 'The weight of :name_en changed from :previous to :weight. Review the distribution of its sub-criteria.',
    },
  },
  'STD-03': {
    title: { ar: 'معيار فرعي جديد', en: 'New sub-criterion' },
    body: {
      ar: 'تمت إضافة المعيار الفرعي :name_ar. أكمل وزنه ونوع تقييمه.',
      en: 'The sub-criterion :name_en was added. Complete its weight and evaluation type.',
    },
  },
  'STD-04': {
    title: { ar: 'تفعيل التقييم على النقاط الاسترشادية', en: 'Guiding-point evaluation enabled' },
    body: {
      ar: 'أصبح المعيار الفرعي :name_ar يُقيَّم على النقاط الاسترشادية. حدّد نقاطه الاسترشادية.',
      en: 'The sub-criterion :name_en is now evaluated on guiding points. Define its guiding points.',
    },
  },
  'STD-05': {
    title: { ar: 'تفعيل التقييم المباشر', en: 'Direct evaluation enabled' },
    body: {
      ar: 'أصبح المعيار الفرعي :name_ar يُقيَّم مباشرة. راجع الإرشادات والمرفقات الخاصة به.',
      en: 'The sub-criterion :name_en is now evaluated directly. Review its guidance and attachments.',
    },
  },
  'STD-07': {
    title: { ar: 'عدم اتساق أوزان المعايير', en: 'Criteria weights are inconsistent' },
    body: {
      ar: 'توجد :count من مشكلات توزيع الأوزان في مسودة إصدار المعايير :version. عالجها قبل النشر.',
      en: 'Draft criteria version :version has :count weight distribution problems. Fix them before publishing.',
    },
  },
  'STD-08': {
    title: { ar: 'تعديل المعايير المنشورة', en: 'Published criteria edited' },
    body: {
      ar: 'عُدّلت المعايير المنشورة بعدد :count من التغييرات، وطُبّقت على :scope_ar.',
      en: 'The published criteria were edited with :count changes, applied to :scope_en.',
    },
  },
}

export const criteriaScopeLabels = {
  all: { ar: 'جميع التقييمات', en: 'all assessments' },
  new_only: { ar: 'الطلبات الجديدة فقط', en: 'new requests only' },
  reset_open: { ar: 'إعادة تعيين الطلبات المفتوحة', en: 'reset of the open requests' },
}
