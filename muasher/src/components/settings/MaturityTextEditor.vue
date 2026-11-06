<script setup>
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { MATURITY_BANDS, MATURITY_TEXT_MAX } from '@/constants/insights'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  initial: { type: Object, required: true },
  mains: { type: Array, required: true },
  errors: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'cancel'])
const { t, n, locale } = useI18n()
const editor = reactive({ ...props.initial })

const bandOptions = computed(() =>
  MATURITY_BANDS.map((band) => ({ value: band, label: t(`insights.bands.${band}`) })),
)
const mainOptions = computed(() =>
  props.mains.map((main) => ({
    value: main.code,
    label: `${main.code} · ${localizedText(main.name, locale.value)}`,
  })),
)

function counter(value) {
  return t('settings.maturityTexts.counter', { count: n(value.length), max: n(MATURITY_TEXT_MAX) })
}

function errorOf(field) {
  const messages = props.errors[field]
  return Array.isArray(messages) ? messages[0] : (messages ?? '')
}
</script>

<template>
  <form
    class="maturity-editor"
    novalidate
    data-testid="maturity-editor"
    @submit.prevent="emit('save', { ...editor })"
  >
    <div class="settings-form__grid">
      <AppSelect
        v-model="editor.band"
        name="maturity-band"
        :label="t('settings.maturityTexts.band')"
        :options="bandOptions"
        :disabled="editor.generic"
        required
      />
      <AppSelect
        v-if="!editor.generic"
        v-model="editor.mainCriterionCode"
        name="maturity-main"
        :label="t('settings.maturityTexts.criterion')"
        :options="mainOptions"
        :error="errorOf('mainCriterionCode')"
        required
      />
    </div>
    <div class="settings-form__grid">
      <div>
        <BaseTextarea
          v-model="editor.ar"
          name="maturity-text-ar"
          :label="t('settings.maturityTexts.textAr')"
          input-dir="rtl"
          :error="errorOf('text.ar')"
          required
        />
        <p class="maturity-editor__counter">
          <bdi dir="ltr">{{ counter(editor.ar) }}</bdi>
        </p>
      </div>
      <div>
        <BaseTextarea
          v-model="editor.en"
          name="maturity-text-en"
          :label="t('settings.maturityTexts.textEn')"
          input-dir="ltr"
          :error="errorOf('text.en')"
          required
        />
        <p class="maturity-editor__counter">
          <bdi dir="ltr">{{ counter(editor.en) }}</bdi>
        </p>
      </div>
    </div>
    <div class="path-settings__actions">
      <BaseButton type="submit" variant="primary" icon="check" :loading="saving">
        {{ t('settings.maturityTexts.save') }}
      </BaseButton>
      <BaseButton type="button" variant="ghost" @click="emit('cancel')">
        {{ t('actions.cancel') }}
      </BaseButton>
    </div>
  </form>
</template>
