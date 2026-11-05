<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseSelect from '@/components/form/BaseSelect.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  siteSettings: { type: Object, default: null },
  status: { type: String, default: 'loading' },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'retry'])

const { t } = useI18n()
const toast = useToast()

const PLATFORMS = [
  'x',
  'linkedin',
  'youtube',
  'facebook',
  'instagram',
  'snapchat',
  'tiktok',
  'whatsapp',
]

const platformOptions = computed(() =>
  PLATFORMS.map((platform) => ({
    value: platform,
    label: t(`settings.homepage.siteSettings.platforms.${platform}`),
  })),
)

const draft = ref(null)

function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

function blankModel() {
  return {
    footerNote: { ar: '', en: '' },
    contact: { email: '', phone: '', address: { ar: '', en: '' } },
    social: [],
    quickLinks: [],
  }
}

function hydrate(source) {
  const base = blankModel()
  if (!source) {
    draft.value = base
    return
  }
  draft.value = {
    footerNote: { ...base.footerNote, ...clone(source.footerNote ?? {}) },
    contact: {
      email: source.contact?.email ?? '',
      phone: source.contact?.phone ?? '',
      address: { ...base.contact.address, ...clone(source.contact?.address ?? {}) },
    },
    social: clone(source.social ?? []).map((row) => ({
      platform: row.platform ?? 'x',
      url: row.url ?? '',
    })),
    quickLinks: clone(source.quickLinks ?? []).map((row) => ({
      label: { ar: row.label?.ar ?? '', en: row.label?.en ?? '' },
      url: row.url ?? '',
    })),
  }
}

watch(
  () => props.siteSettings,
  (value) => hydrate(value),
  { immediate: true },
)

function addSocial() {
  draft.value.social.push({ platform: 'x', url: '' })
}

function removeSocial(index) {
  draft.value.social.splice(index, 1)
}

function addQuickLink() {
  draft.value.quickLinks.push({ label: { ar: '', en: '' }, url: '' })
}

function removeQuickLink(index) {
  draft.value.quickLinks.splice(index, 1)
}

function moveQuickLink(index, direction) {
  const target = index + direction
  if (target < 0 || target >= draft.value.quickLinks.length) return
  const list = draft.value.quickLinks
  ;[list[index], list[target]] = [list[target], list[index]]
}

const emailInvalid = computed(() => {
  const email = draft.value?.contact?.email?.trim()
  if (!email) return false
  return !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
})

function onSubmit() {
  if (emailInvalid.value) {
    toast.error(t('settings.homepage.siteSettings.emailInvalid'))
    return
  }
  emit('save', clone(draft.value))
}
</script>

<template>
  <section class="site-settings" aria-labelledby="site-settings-heading">
    <header class="site-settings__header">
      <h2 id="site-settings-heading" class="site-settings__title">
        {{ t('settings.homepage.siteSettings.title') }}
      </h2>
      <p class="site-settings__desc">{{ t('settings.homepage.siteSettings.description') }}</p>
    </header>

    <div v-if="status === 'loading'" class="site-settings__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>

    <ErrorState
      v-else-if="status === 'error'"
      :message="t('settings.homepage.siteSettings.error')"
      @retry="emit('retry')"
    />

    <form
      v-else-if="draft"
      class="site-settings__form"
      :aria-busy="busy || undefined"
      @submit.prevent="onSubmit"
    >
      <fieldset class="site-settings__group">
        <legend class="site-settings__legend">
          {{ t('settings.homepage.siteSettings.footerNote') }}
        </legend>
        <div class="site-settings__grid">
          <BaseTextarea
            v-model="draft.footerNote.ar"
            name="footerNoteAr"
            :rows="2"
            :label="t('settings.homepage.siteSettings.footerNoteAr')"
          />
          <BaseTextarea
            v-model="draft.footerNote.en"
            name="footerNoteEn"
            :rows="2"
            :label="t('settings.homepage.siteSettings.footerNoteEn')"
          />
        </div>
      </fieldset>

      <fieldset class="site-settings__group">
        <legend class="site-settings__legend">
          {{ t('settings.homepage.siteSettings.contact') }}
        </legend>
        <div class="site-settings__grid">
          <BaseInput
            v-model="draft.contact.email"
            name="contactEmail"
            type="email"
            :label="t('settings.homepage.siteSettings.email')"
            :error="emailInvalid ? t('settings.homepage.siteSettings.emailInvalid') : ''"
          />
          <BaseInput
            v-model="draft.contact.phone"
            name="contactPhone"
            :label="t('settings.homepage.siteSettings.phone')"
          />
          <BaseInput
            v-model="draft.contact.address.ar"
            name="contactAddressAr"
            :label="t('settings.homepage.siteSettings.addressAr')"
          />
          <BaseInput
            v-model="draft.contact.address.en"
            name="contactAddressEn"
            :label="t('settings.homepage.siteSettings.addressEn')"
          />
        </div>
      </fieldset>

      <fieldset class="site-settings__group">
        <legend class="site-settings__legend">
          {{ t('settings.homepage.siteSettings.social') }}
        </legend>
        <ul v-if="draft.social.length" class="site-settings__rows">
          <li
            v-for="(row, index) in draft.social"
            :key="`social-${index}`"
            class="site-settings__row"
          >
            <BaseSelect
              v-model="row.platform"
              :name="`socialPlatform-${index}`"
              :label="t('settings.homepage.siteSettings.platform')"
              :options="platformOptions"
            />
            <BaseInput
              v-model="row.url"
              :name="`socialUrl-${index}`"
              :label="t('settings.homepage.siteSettings.url')"
              :hint="t('settings.homepage.siteSettings.urlHint')"
            />
            <BaseButton
              variant="ghost"
              size="sm"
              icon="fa-solid fa-trash-can"
              icon-only
              :disabled="busy"
              :aria-label="t('settings.homepage.siteSettings.removeSocial')"
              @click="removeSocial(index)"
            />
          </li>
        </ul>
        <p v-else class="site-settings__empty">
          {{ t('settings.homepage.siteSettings.noSocial') }}
        </p>
        <BaseButton
          variant="secondary"
          size="sm"
          icon="fa-solid fa-plus"
          :disabled="busy"
          @click="addSocial"
        >
          {{ t('settings.homepage.siteSettings.addSocial') }}
        </BaseButton>
      </fieldset>

      <fieldset class="site-settings__group">
        <legend class="site-settings__legend">
          {{ t('settings.homepage.siteSettings.quickLinks') }}
        </legend>
        <ul v-if="draft.quickLinks.length" class="site-settings__rows">
          <li
            v-for="(row, index) in draft.quickLinks"
            :key="`quick-${index}`"
            class="site-settings__row site-settings__row--stack"
          >
            <div class="site-settings__grid">
              <BaseInput
                v-model="row.label.ar"
                :name="`quickLabelAr-${index}`"
                :label="t('settings.homepage.siteSettings.linkLabelAr')"
              />
              <BaseInput
                v-model="row.label.en"
                :name="`quickLabelEn-${index}`"
                :label="t('settings.homepage.siteSettings.linkLabelEn')"
              />
            </div>
            <div class="site-settings__row site-settings__row--inline">
              <BaseInput
                v-model="row.url"
                :name="`quickUrl-${index}`"
                :label="t('settings.homepage.siteSettings.url')"
                :hint="t('settings.homepage.siteSettings.urlHint')"
              />
              <div class="site-settings__row-actions">
                <BaseButton
                  variant="ghost"
                  size="sm"
                  icon="fa-solid fa-arrow-up-long"
                  icon-only
                  :disabled="busy || index === 0"
                  :aria-label="t('settings.homepage.siteSettings.moveUp')"
                  @click="moveQuickLink(index, -1)"
                />
                <BaseButton
                  variant="ghost"
                  size="sm"
                  icon="fa-solid fa-arrow-down-long"
                  icon-only
                  :disabled="busy || index === draft.quickLinks.length - 1"
                  :aria-label="t('settings.homepage.siteSettings.moveDown')"
                  @click="moveQuickLink(index, 1)"
                />
                <BaseButton
                  variant="ghost"
                  size="sm"
                  icon="fa-solid fa-trash-can"
                  icon-only
                  :disabled="busy"
                  :aria-label="t('settings.homepage.siteSettings.removeLink')"
                  @click="removeQuickLink(index)"
                />
              </div>
            </div>
          </li>
        </ul>
        <p v-else class="site-settings__empty">{{ t('settings.homepage.siteSettings.noLinks') }}</p>
        <BaseButton
          variant="secondary"
          size="sm"
          icon="fa-solid fa-plus"
          :disabled="busy"
          @click="addQuickLink"
        >
          {{ t('settings.homepage.siteSettings.addLink') }}
        </BaseButton>
      </fieldset>

      <div class="site-settings__footer">
        <BaseButton type="submit" variant="primary" :loading="busy">
          {{ t('settings.homepage.siteSettings.save') }}
        </BaseButton>
      </div>
    </form>
  </section>
</template>
