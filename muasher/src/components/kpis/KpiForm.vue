<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  object as yupObject,
  string as yupString,
  number as yupNumber,
  boolean as yupBoolean,
} from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseSelect from '@/components/form/BaseSelect.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import SearchSelect from '@/components/form/SearchSelect.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import KpiCard from '@/components/kpis/KpiCard.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useKpisAdmin } from '@/composables/useKpisAdmin'
import { KPI_CATEGORY_KEYS, KPI_STATUS_KEYS } from '@/constants/kpiCategories'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  kpi: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const { t, locale } = useI18n()
const { colorOptions, iconOptions, colorLabel, categoryOptions, statusOptions } = useKpisAdmin()

const isEdit = computed(() => Boolean(props.kpi))

const schema = computed(() =>
  yupObject({
    nameAr: yupString().required(t('validation.required')).min(2, t('validation.minChars', { min: 2 })),
    nameEn: yupString().required(t('validation.required')).min(2, t('validation.minChars', { min: 2 })),
    descriptionAr: yupString().default(''),
    descriptionEn: yupString().default(''),
    value: yupNumber()
      .transform((v, o) => (o === '' ? undefined : v))
      .typeError(t('validation.number'))
      .required(t('validation.required'))
      .min(0, t('validation.min', { min: 0 })),
    target: yupNumber()
      .transform((v, o) => (o === '' ? null : v))
      .typeError(t('validation.number'))
      .nullable()
      .min(0, t('validation.min', { min: 0 })),
    unitAr: yupString().required(t('validation.required')),
    unitEn: yupString().required(t('validation.required')),
    baseline: yupNumber()
      .transform((v, o) => (o === '' ? null : v))
      .typeError(t('validation.number'))
      .nullable()
      .min(0, t('validation.min', { min: 0 })),
    category: yupString()
      .oneOf([...KPI_CATEGORY_KEYS], t('kpis.admin.validation.categoryRequired'))
      .required(t('kpis.admin.validation.categoryRequired')),
    status: yupString()
      .oneOf([...KPI_STATUS_KEYS], t('kpis.admin.validation.statusRequired'))
      .required(t('kpis.admin.validation.statusRequired')),
    ownerAr: yupString().default(''),
    ownerEn: yupString().default(''),
    dataSourceAr: yupString().default(''),
    dataSourceEn: yupString().default(''),
    lastUpdate: yupString().default(''),
    externalLink: yupString().url(t('validation.url')).default(''),
    calcMethodAr: yupString().default(''),
    calcMethodEn: yupString().default(''),
    notesAr: yupString().default(''),
    notesEn: yupString().default(''),
    icon: yupString().required(t('kpis.admin.validation.iconRequired')),
    color: yupString().required(t('kpis.admin.validation.colorRequired')),
    badgeColor: yupString().required(t('kpis.admin.validation.colorRequired')),
    cardColor: yupString().required(t('kpis.admin.validation.colorRequired')),
    showOnHomepage: yupBoolean(),
    displayOrder: yupNumber()
      .transform((v, o) => (o === '' ? undefined : v))
      .typeError(t('validation.number'))
      .min(1, t('validation.min', { min: 1 }))
      .nullable(),
    published: yupBoolean(),
  }),
)

function emptyValues() {
  return {
    nameAr: '',
    nameEn: '',
    descriptionAr: '',
    descriptionEn: '',
    value: '',
    target: '',
    baseline: '',
    unitAr: '',
    unitEn: '',
    category: '',
    status: '',
    ownerAr: '',
    ownerEn: '',
    dataSourceAr: '',
    dataSourceEn: '',
    lastUpdate: '',
    externalLink: '',
    calcMethodAr: '',
    calcMethodEn: '',
    notesAr: '',
    notesEn: '',
    icon: 'fa-solid fa-chart-simple',
    color: 'primary',
    badgeColor: 'primary',
    cardColor: 'primary',
    showOnHomepage: false,
    displayOrder: '',
    published: false,
  }
}

function fromKpi(kpi) {
  if (!kpi) return emptyValues()
  return {
    nameAr: kpi.name?.ar ?? '',
    nameEn: kpi.name?.en ?? '',
    descriptionAr: kpi.description?.ar ?? '',
    descriptionEn: kpi.description?.en ?? '',
    value: kpi.value ?? '',
    target: kpi.target ?? '',
    baseline: kpi.baseline ?? '',
    unitAr: kpi.unit?.ar ?? '',
    unitEn: kpi.unit?.en ?? '',
    category: kpi.category ?? '',
    status: kpi.status ?? '',
    ownerAr: kpi.owner?.ar ?? '',
    ownerEn: kpi.owner?.en ?? '',
    dataSourceAr: kpi.dataSource?.ar ?? '',
    dataSourceEn: kpi.dataSource?.en ?? '',
    lastUpdate: kpi.lastUpdate ?? '',
    externalLink: kpi.externalLink ?? '',
    calcMethodAr: kpi.calcMethod?.ar ?? '',
    calcMethodEn: kpi.calcMethod?.en ?? '',
    notesAr: kpi.notes?.ar ?? '',
    notesEn: kpi.notes?.en ?? '',
    icon: kpi.icon ?? 'fa-solid fa-chart-simple',
    color: kpi.color ?? 'primary',
    badgeColor: kpi.badgeColor ?? kpi.color ?? 'primary',
    cardColor: kpi.cardColor ?? kpi.color ?? 'primary',
    showOnHomepage: Boolean(kpi.showOnHomepage),
    displayOrder: kpi.order ?? '',
    published: Boolean(kpi.published),
  }
}

const form = useValidatedForm({
  schema,
  initialValues: fromKpi(props.kpi),
  action: 'kpis.form.submit',
  submit: async (values) => {
    const payload = {
      name: { ar: values.nameAr.trim(), en: values.nameEn.trim() },
      description: {
        ar: (values.descriptionAr ?? '').trim(),
        en: (values.descriptionEn ?? '').trim(),
      },
      value: Number(values.value),
      target: values.target === '' || values.target === null ? null : Number(values.target),
      baseline: values.baseline === '' || values.baseline === null ? null : Number(values.baseline),
      unit: { ar: values.unitAr.trim(), en: values.unitEn.trim() },
      category: values.category,
      status: values.status,
      owner: { ar: (values.ownerAr ?? '').trim(), en: (values.ownerEn ?? '').trim() },
      dataSource: {
        ar: (values.dataSourceAr ?? '').trim(),
        en: (values.dataSourceEn ?? '').trim(),
      },
      lastUpdate: values.lastUpdate || null,
      externalLink: (values.externalLink ?? '').trim(),
      calcMethod: {
        ar: (values.calcMethodAr ?? '').trim(),
        en: (values.calcMethodEn ?? '').trim(),
      },
      notes: { ar: (values.notesAr ?? '').trim(), en: (values.notesEn ?? '').trim() },
      icon: values.icon,
      color: values.color,
      badgeColor: values.badgeColor,
      cardColor: values.cardColor,
      showOnHomepage: Boolean(values.showOnHomepage),
      order: values.displayOrder === '' || values.displayOrder === null ? undefined : Number(values.displayOrder),
      published: Boolean(values.published),
    }
    emit('saved', { id: props.kpi?.id ?? null, payload })
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: fromKpi(props.kpi) })
  },
)

const [nameAr] = form.defineField('nameAr')
const [nameEn] = form.defineField('nameEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [value] = form.defineField('value')
const [target] = form.defineField('target')
const [baseline] = form.defineField('baseline')
const [unitAr] = form.defineField('unitAr')
const [unitEn] = form.defineField('unitEn')
const [category] = form.defineField('category')
const [status] = form.defineField('status')
const [ownerAr] = form.defineField('ownerAr')
const [ownerEn] = form.defineField('ownerEn')
const [dataSourceAr] = form.defineField('dataSourceAr')
const [dataSourceEn] = form.defineField('dataSourceEn')
const [lastUpdate] = form.defineField('lastUpdate')
const [externalLink] = form.defineField('externalLink')
const [calcMethodAr] = form.defineField('calcMethodAr')
const [calcMethodEn] = form.defineField('calcMethodEn')
const [notesAr] = form.defineField('notesAr')
const [notesEn] = form.defineField('notesEn')
const [icon] = form.defineField('icon')
const [color] = form.defineField('color')
const [badgeColor] = form.defineField('badgeColor')
const [cardColor] = form.defineField('cardColor')
const [showOnHomepage] = form.defineField('showOnHomepage')
const [displayOrder] = form.defineField('displayOrder')
const [published] = form.defineField('published')

function localizedPreview(ar, en) {
  return locale.value === 'en' ? en || ar : ar || en
}

const previewKpi = computed(() => ({
  id: 'preview',
  name: { ar: nameAr.value, en: nameEn.value },
  description: { ar: descriptionAr.value, en: descriptionEn.value },
  value: value.value === '' ? 0 : Number(value.value),
  target: target.value === '' ? null : Number(target.value),
  baseline: baseline.value === '' ? null : Number(baseline.value),
  unit: { ar: unitAr.value, en: unitEn.value },
  category: category.value,
  status: status.value,
  icon: icon.value,
  color: cardColor.value,
  badgeColor: badgeColor.value,
  cardColor: cardColor.value,
}))

function previewLocalized(content) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return localizedPreview(content.ar, content.en)
}
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="isEdit ? t('kpis.admin.form.editTitle') : t('kpis.admin.form.createTitle')"
    :submit-label="isEdit ? t('actions.save') : t('kpis.admin.form.create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="kpi-form">
      <div class="kpi-form__grid">
        <fieldset class="kpi-form__section">
          <legend class="kpi-form__legend">{{ t('kpis.admin.form.nameGroup') }}</legend>
          <BaseInput
            v-model="nameAr"
            name="nameAr"
            :label="t('kpis.admin.fields.nameAr')"
            :placeholder="t('kpis.admin.placeholders.nameAr')"
            :error="form.errors.value.nameAr"
            required
          />
          <BaseInput
            v-model="nameEn"
            name="nameEn"
            :label="t('kpis.admin.fields.nameEn')"
            :placeholder="t('kpis.admin.placeholders.nameEn')"
            :error="form.errors.value.nameEn"
            required
          />
        </fieldset>

        <fieldset class="kpi-form__section">
          <legend class="kpi-form__legend">{{ t('kpis.admin.form.descriptionGroup') }}</legend>
          <BaseTextarea
            v-model="descriptionAr"
            name="descriptionAr"
            :rows="3"
            :label="t('kpis.admin.fields.descriptionAr')"
            :placeholder="t('kpis.admin.placeholders.descriptionAr')"
            :error="form.errors.value.descriptionAr"
          />
          <BaseTextarea
            v-model="descriptionEn"
            name="descriptionEn"
            :rows="3"
            :label="t('kpis.admin.fields.descriptionEn')"
            :placeholder="t('kpis.admin.placeholders.descriptionEn')"
            :error="form.errors.value.descriptionEn"
          />
        </fieldset>

        <fieldset class="kpi-form__section">
          <legend class="kpi-form__legend">{{ t('kpis.admin.form.valueGroup') }}</legend>
          <div class="kpi-form__row">
            <BaseInput
              v-model="value"
              name="value"
              type="number"
              :label="t('kpis.admin.fields.value')"
              :placeholder="t('kpis.admin.placeholders.value')"
              :error="form.errors.value.value"
              required
            />
            <BaseInput
              v-model="target"
              name="target"
              type="number"
              :label="t('kpis.admin.fields.target')"
              :placeholder="t('kpis.admin.placeholders.target')"
              :hint="t('kpis.admin.fields.targetHint')"
              :error="form.errors.value.target"
            />
          </div>
          <div class="kpi-form__row">
            <BaseInput
              v-model="unitAr"
              name="unitAr"
              :label="t('kpis.admin.fields.unitAr')"
              :placeholder="t('kpis.admin.placeholders.unitAr')"
              :error="form.errors.value.unitAr"
              required
            />
            <BaseInput
              v-model="unitEn"
              name="unitEn"
              :label="t('kpis.admin.fields.unitEn')"
              :placeholder="t('kpis.admin.placeholders.unitEn')"
              :error="form.errors.value.unitEn"
              required
            />
          </div>
          <BaseInput
            v-model="baseline"
            name="baseline"
            type="number"
            :label="t('kpis.admin.fields.baseline')"
            :placeholder="t('kpis.admin.placeholders.baseline')"
            :hint="t('kpis.admin.fields.baselineHint')"
            :error="form.errors.value.baseline"
          />
        </fieldset>

        <fieldset class="kpi-form__section">
          <legend class="kpi-form__legend">{{ t('kpis.admin.form.classificationGroup') }}</legend>
          <div class="kpi-form__field">
            <SearchSelect
              v-model="category"
              :options="categoryOptions"
              :label="t('kpis.admin.fields.category')"
              :placeholder="t('kpis.admin.placeholders.category')"
            />
            <p v-if="form.errors.value.category" class="kpi-form__error" role="alert">
              {{ form.errors.value.category }}
            </p>
          </div>
          <BaseSelect
            :model-value="status"
            name="status"
            :options="statusOptions"
            :label="t('kpis.admin.fields.status')"
            :error="form.errors.value.status"
            required
            @update:model-value="status = $event"
          />
        </fieldset>

        <fieldset class="kpi-form__section">
          <legend class="kpi-form__legend">{{ t('kpis.admin.form.ownershipGroup') }}</legend>
          <div class="kpi-form__row">
            <BaseInput
              v-model="ownerAr"
              name="ownerAr"
              :label="t('kpis.admin.fields.ownerAr')"
              :placeholder="t('kpis.admin.placeholders.ownerAr')"
              :error="form.errors.value.ownerAr"
            />
            <BaseInput
              v-model="ownerEn"
              name="ownerEn"
              :label="t('kpis.admin.fields.ownerEn')"
              :placeholder="t('kpis.admin.placeholders.ownerEn')"
              :error="form.errors.value.ownerEn"
            />
          </div>
          <div class="kpi-form__row">
            <BaseInput
              v-model="dataSourceAr"
              name="dataSourceAr"
              :label="t('kpis.admin.fields.dataSourceAr')"
              :placeholder="t('kpis.admin.placeholders.dataSourceAr')"
              :error="form.errors.value.dataSourceAr"
            />
            <BaseInput
              v-model="dataSourceEn"
              name="dataSourceEn"
              :label="t('kpis.admin.fields.dataSourceEn')"
              :placeholder="t('kpis.admin.placeholders.dataSourceEn')"
              :error="form.errors.value.dataSourceEn"
            />
          </div>
          <div class="kpi-form__row">
            <BaseDatePicker
              :model-value="lastUpdate"
              name="lastUpdate"
              :label="t('kpis.admin.fields.lastUpdate')"
              :hint="t('kpis.admin.fields.lastUpdateHint')"
              :error="form.errors.value.lastUpdate"
              @update:model-value="lastUpdate = $event"
            />
            <BaseInput
              v-model="externalLink"
              name="externalLink"
              type="url"
              :label="t('kpis.admin.fields.externalLink')"
              :placeholder="t('kpis.admin.placeholders.externalLink')"
              :hint="t('kpis.admin.fields.externalLinkHint')"
              :error="form.errors.value.externalLink"
            />
          </div>
        </fieldset>

        <fieldset class="kpi-form__section">
          <legend class="kpi-form__legend">{{ t('kpis.admin.form.methodGroup') }}</legend>
          <BaseTextarea
            v-model="calcMethodAr"
            name="calcMethodAr"
            :rows="2"
            :label="t('kpis.admin.fields.calcMethodAr')"
            :placeholder="t('kpis.admin.placeholders.calcMethodAr')"
            :error="form.errors.value.calcMethodAr"
          />
          <BaseTextarea
            v-model="calcMethodEn"
            name="calcMethodEn"
            :rows="2"
            :label="t('kpis.admin.fields.calcMethodEn')"
            :placeholder="t('kpis.admin.placeholders.calcMethodEn')"
            :error="form.errors.value.calcMethodEn"
          />
          <BaseTextarea
            v-model="notesAr"
            name="notesAr"
            :rows="2"
            :label="t('kpis.admin.fields.notesAr')"
            :placeholder="t('kpis.admin.placeholders.notesAr')"
            :error="form.errors.value.notesAr"
          />
          <BaseTextarea
            v-model="notesEn"
            name="notesEn"
            :rows="2"
            :label="t('kpis.admin.fields.notesEn')"
            :placeholder="t('kpis.admin.placeholders.notesEn')"
            :error="form.errors.value.notesEn"
          />
        </fieldset>

        <fieldset class="kpi-form__section">
          <legend class="kpi-form__legend">{{ t('kpis.admin.form.displayGroup') }}</legend>
          <div class="kpi-form__field">
            <div class="kpi-form__icon-field">
              <span class="kpi-form__icon-preview" aria-hidden="true">
                <i :class="icon"></i>
              </span>
              <SearchSelect
                v-model="icon"
                class="kpi-form__icon-select"
                :options="iconOptions"
                :label="t('kpis.admin.fields.icon')"
                :placeholder="t('kpis.admin.placeholders.icon')"
              />
            </div>
            <p v-if="form.errors.value.icon" class="kpi-form__error" role="alert">
              {{ form.errors.value.icon }}
            </p>
          </div>

          <div class="kpi-form__field">
            <span id="kpi-form-color-label" class="kpi-form__label">
              {{ t('kpis.admin.fields.color') }}
            </span>
            <div
              class="kpi-form__swatches"
              role="radiogroup"
              :aria-label="t('kpis.admin.fields.color')"
            >
              <button
                v-for="option in colorOptions"
                :key="option.value"
                type="button"
                class="kpi-form__swatch"
                :class="[`kpi-form__swatch--${option.value}`, { 'is-selected': color === option.value }]"
                role="radio"
                :aria-checked="color === option.value"
                :aria-label="option.label"
                @click="color = option.value"
              >
                <span class="kpi-form__swatch-dot" aria-hidden="true"></span>
                <span class="kpi-form__swatch-label">{{ option.label }}</span>
                <i
                  v-if="color === option.value"
                  class="fa-solid fa-check kpi-form__swatch-check"
                  aria-hidden="true"
                ></i>
              </button>
            </div>
            <p v-if="form.errors.value.color" class="kpi-form__error" role="alert">
              {{ form.errors.value.color }}
            </p>
          </div>

          <div class="kpi-form__field">
            <span class="kpi-form__label">{{ t('kpis.admin.fields.cardColor') }}</span>
            <div
              class="kpi-form__swatches"
              role="radiogroup"
              :aria-label="t('kpis.admin.fields.cardColor')"
            >
              <button
                v-for="option in colorOptions"
                :key="option.value"
                type="button"
                class="kpi-form__swatch"
                :class="[`kpi-form__swatch--${option.value}`, { 'is-selected': cardColor === option.value }]"
                role="radio"
                :aria-checked="cardColor === option.value"
                :aria-label="option.label"
                @click="cardColor = option.value"
              >
                <span class="kpi-form__swatch-dot" aria-hidden="true"></span>
                <span class="kpi-form__swatch-label">{{ option.label }}</span>
                <i
                  v-if="cardColor === option.value"
                  class="fa-solid fa-check kpi-form__swatch-check"
                  aria-hidden="true"
                ></i>
              </button>
            </div>
            <p v-if="form.errors.value.cardColor" class="kpi-form__error" role="alert">
              {{ form.errors.value.cardColor }}
            </p>
          </div>

          <div class="kpi-form__field">
            <span class="kpi-form__label">{{ t('kpis.admin.fields.badgeColor') }}</span>
            <div
              class="kpi-form__swatches"
              role="radiogroup"
              :aria-label="t('kpis.admin.fields.badgeColor')"
            >
              <button
                v-for="option in colorOptions"
                :key="option.value"
                type="button"
                class="kpi-form__swatch"
                :class="[`kpi-form__swatch--${option.value}`, { 'is-selected': badgeColor === option.value }]"
                role="radio"
                :aria-checked="badgeColor === option.value"
                :aria-label="option.label"
                @click="badgeColor = option.value"
              >
                <span class="kpi-form__swatch-dot" aria-hidden="true"></span>
                <span class="kpi-form__swatch-label">{{ option.label }}</span>
                <i
                  v-if="badgeColor === option.value"
                  class="fa-solid fa-check kpi-form__swatch-check"
                  aria-hidden="true"
                ></i>
              </button>
            </div>
            <p v-if="form.errors.value.badgeColor" class="kpi-form__error" role="alert">
              {{ form.errors.value.badgeColor }}
            </p>
          </div>

          <BaseInput
            v-model="displayOrder"
            name="displayOrder"
            type="number"
            :label="t('kpis.admin.fields.displayOrder')"
            :placeholder="t('kpis.admin.placeholders.displayOrder')"
            :hint="t('kpis.admin.fields.displayOrderHint')"
            :error="form.errors.value.displayOrder"
          />

          <ToggleSwitch
            :model-value="showOnHomepage"
            name="showOnHomepage"
            :label="t('kpis.admin.fields.showOnHomepage')"
            :description="t('kpis.admin.fields.showOnHomepageHint')"
            @update:model-value="showOnHomepage = $event"
          />

          <ToggleSwitch
            :model-value="published"
            name="published"
            :label="t('kpis.admin.fields.published')"
            :description="t('kpis.admin.fields.publishedHint')"
            @update:model-value="published = $event"
          />
        </fieldset>
      </div>

      <aside class="kpi-form__preview" aria-live="polite">
        <p class="kpi-form__preview-label">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
          {{ t('kpis.admin.form.previewLabel') }}
          <span class="kpi-form__preview-color">{{ colorLabel(color) }}</span>
        </p>
        <KpiCard :kpi="previewKpi" :localized="previewLocalized" />
      </aside>
    </div>
  </FormModal>
</template>
