<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, string as yupString } from 'yup'
import BaseModal from '@/components/modal/BaseModal.vue'
import FormModal from '@/components/modal/FormModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  section: { type: Object, default: null },
  items: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  localized: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue', 'upsert', 'remove', 'reorder'])

const { t } = useI18n()
const { confirm } = useConfirm()

const orderedItems = computed(() =>
  [...props.items].sort((a, b) => a.order - b.order),
)

function close() {
  emit('update:modelValue', false)
}

const editOpen = ref(false)
const activeItem = ref(null)

const schema = computed(() =>
  yupObject({
    titleAr: yupString().trim().required(t('validation.required')),
    titleEn: yupString().trim().required(t('validation.required')),
    descriptionAr: yupString().trim(),
    descriptionEn: yupString().trim(),
    ctaLabelAr: yupString().trim(),
    ctaLabelEn: yupString().trim(),
    ctaUrl: yupString().trim(),
    icon: yupString().trim(),
    image: yupString().trim(),
  }),
)

const form = useValidatedForm({
  schema,
  initialValues: {
    titleAr: '', titleEn: '', descriptionAr: '', descriptionEn: '',
    ctaLabelAr: '', ctaLabelEn: '', ctaUrl: '', icon: '', image: '',
  },
  action: 'homepage.item.upsert',
  submit: async (values) => {
    const payload = {
      title: { ar: values.titleAr, en: values.titleEn },
      description: { ar: values.descriptionAr, en: values.descriptionEn },
      ctaLabel: { ar: values.ctaLabelAr, en: values.ctaLabelEn },
      ctaUrl: values.ctaUrl,
      icon: values.icon,
      image: values.image,
    }
    if (activeItem.value) {
      payload.id = activeItem.value.id
      payload.order = activeItem.value.order
      payload.meta = activeItem.value.meta ?? {}
    }
    emit('upsert', payload)
    editOpen.value = false
  },
})

const [titleAr] = form.defineField('titleAr')
const [titleEn] = form.defineField('titleEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [ctaLabelAr] = form.defineField('ctaLabelAr')
const [ctaLabelEn] = form.defineField('ctaLabelEn')
const [ctaUrl] = form.defineField('ctaUrl')
const [icon] = form.defineField('icon')
const [image] = form.defineField('image')

function openCreate() {
  activeItem.value = null
  form.resetForm({
    values: {
      titleAr: '', titleEn: '', descriptionAr: '', descriptionEn: '',
      ctaLabelAr: '', ctaLabelEn: '', ctaUrl: '', icon: '', image: '',
    },
  })
  editOpen.value = true
}

function openEdit(entry) {
  activeItem.value = entry
  form.resetForm({
    values: {
      titleAr: entry.title?.ar ?? '',
      titleEn: entry.title?.en ?? '',
      descriptionAr: entry.description?.ar ?? '',
      descriptionEn: entry.description?.en ?? '',
      ctaLabelAr: entry.ctaLabel?.ar ?? '',
      ctaLabelEn: entry.ctaLabel?.en ?? '',
      ctaUrl: entry.ctaUrl ?? '',
      icon: entry.icon ?? '',
      image: entry.image ?? '',
    },
  })
  editOpen.value = true
}

async function onRemove(entry) {
  const accepted = await confirm({
    title: t('settings.homepage.items.confirm.removeTitle'),
    message: t('settings.homepage.items.confirm.removeMessage', {
      name: props.localized(entry.title) || t('settings.homepage.items.untitled'),
    }),
    confirmLabel: t('settings.homepage.items.remove'),
    variant: 'danger',
  })
  if (!accepted) return
  emit('remove', entry.id)
}

function move(entry, direction) {
  const list = orderedItems.value
  const index = list.findIndex((candidate) => candidate.id === entry.id)
  const target = index + direction
  if (index === -1 || target < 0 || target >= list.length) return
  const ids = list.map((candidate) => candidate.id)
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  emit('reorder', ids)
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="section ? t('settings.homepage.items.title', { section: localized(section.title) || t(`settings.homepage.keys.${section.key}`, section.key) }) : ''"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="homepage-items" :aria-busy="busy || undefined">
      <p class="homepage-items__hint">{{ t('settings.homepage.items.hint') }}</p>

      <ul v-if="orderedItems.length" class="homepage-items__list">
        <li v-for="(entry, index) in orderedItems" :key="entry.id" class="homepage-items__row">
          <span class="homepage-items__order">{{ index + 1 }}</span>
          <span v-if="entry.icon" class="homepage-items__icon"><i :class="entry.icon" aria-hidden="true"></i></span>
          <div class="homepage-items__body">
            <span class="homepage-items__name">{{ localized(entry.title) || t('settings.homepage.items.untitled') }}</span>
            <span v-if="localized(entry.description)" class="homepage-items__summary">{{ localized(entry.description) }}</span>
          </div>
          <div class="homepage-items__row-actions">
            <BaseButton
              variant="ghost"
              size="sm"
              icon="fa-solid fa-arrow-up-long"
              icon-only
              :disabled="busy || index === 0"
              :aria-label="t('settings.homepage.items.moveUp')"
              @click="move(entry, -1)"
            />
            <BaseButton
              variant="ghost"
              size="sm"
              icon="fa-solid fa-arrow-down-long"
              icon-only
              :disabled="busy || index === orderedItems.length - 1"
              :aria-label="t('settings.homepage.items.moveDown')"
              @click="move(entry, 1)"
            />
            <BaseButton
              variant="secondary"
              size="sm"
              icon="fa-solid fa-pen"
              :disabled="busy"
              @click="openEdit(entry)"
            >
              {{ t('actions.edit') }}
            </BaseButton>
            <BaseButton
              variant="ghost"
              size="sm"
              icon="fa-solid fa-trash-can"
              icon-only
              :disabled="busy"
              :aria-label="t('settings.homepage.items.remove')"
              @click="onRemove(entry)"
            />
          </div>
        </li>
      </ul>

      <EmptyState
        v-else
        icon="fa-solid fa-layer-group"
        :title="t('settings.homepage.items.empty.title')"
        :message="t('settings.homepage.items.empty.message')"
      />

      <div class="homepage-items__footer">
        <BaseButton
          variant="primary"
          size="sm"
          icon="fa-solid fa-plus"
          :disabled="busy"
          @click="openCreate"
        >
          {{ t('settings.homepage.items.add') }}
        </BaseButton>
        <BaseButton variant="secondary" size="sm" @click="close">
          {{ t('actions.close') }}
        </BaseButton>
      </div>
    </div>

    <FormModal
      v-model="editOpen"
      size="lg"
      :busy="busy || form.submitting.value"
      :title="activeItem ? t('settings.homepage.items.editTitle') : t('settings.homepage.items.addTitle')"
      :submit-label="t('actions.save')"
      @submit="form.onSubmit"
    >
      <div class="homepage-items__form">
        <fieldset class="homepage-items__group">
          <legend class="homepage-items__legend">{{ t('settings.homepage.groups.text') }}</legend>
          <div class="homepage-items__grid">
            <BaseInput v-model="titleAr" name="itemTitleAr" :label="t('settings.homepage.fields.titleAr')" :error="form.errors.value.titleAr" required />
            <BaseInput v-model="titleEn" name="itemTitleEn" :label="t('settings.homepage.fields.titleEn')" :error="form.errors.value.titleEn" required />
          </div>
          <BaseTextarea v-model="descriptionAr" name="itemDescriptionAr" :rows="3" :label="t('settings.homepage.fields.descriptionAr')" :error="form.errors.value.descriptionAr" />
          <BaseTextarea v-model="descriptionEn" name="itemDescriptionEn" :rows="3" :label="t('settings.homepage.fields.descriptionEn')" :error="form.errors.value.descriptionEn" />
        </fieldset>

        <fieldset class="homepage-items__group">
          <legend class="homepage-items__legend">{{ t('settings.homepage.groups.cta') }}</legend>
          <div class="homepage-items__grid">
            <BaseInput v-model="ctaLabelAr" name="itemCtaLabelAr" :label="t('settings.homepage.fields.ctaLabelAr')" :error="form.errors.value.ctaLabelAr" />
            <BaseInput v-model="ctaLabelEn" name="itemCtaLabelEn" :label="t('settings.homepage.fields.ctaLabelEn')" :error="form.errors.value.ctaLabelEn" />
          </div>
          <BaseInput v-model="ctaUrl" name="itemCtaUrl" :label="t('settings.homepage.fields.ctaUrl')" :hint="t('settings.homepage.fields.ctaUrlHint')" :error="form.errors.value.ctaUrl" />
        </fieldset>

        <fieldset class="homepage-items__group">
          <legend class="homepage-items__legend">{{ t('settings.homepage.groups.media') }}</legend>
          <div class="homepage-items__grid">
            <BaseInput v-model="icon" name="itemIcon" :label="t('settings.homepage.fields.icon')" :hint="t('settings.homepage.fields.iconHint')" :error="form.errors.value.icon" />
            <BaseInput v-model="image" name="itemImage" :label="t('settings.homepage.fields.image')" :hint="t('settings.homepage.fields.imageHint')" :error="form.errors.value.image" />
          </div>
        </fieldset>
      </div>
    </FormModal>
  </BaseModal>
</template>
