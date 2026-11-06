<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import MaturityTextEditor from '@/components/settings/MaturityTextEditor.vue'
import MaturityBandChip from '@/components/insights/MaturityBandChip.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useMaturityTexts } from '@/composables/useMaturityTexts'
import { formatRiyadhDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const texts = useMaturityTexts()
const { status, generic, overrides, mains, editor, errors, saving } = texts

function mainName(code) {
  const main = mains.value.find((item) => item.code === code)
  return main ? localizedText(main.name, locale.value) : ''
}

useHead({ title: () => t('settings.maturityTexts.title') })
onMounted(texts.load)
</script>

<template>
  <SettingsSection
    :title="t('settings.maturityTexts.title')"
    :description="t('settings.maturityTexts.description')"
    :status="status"
    :error-message="t('settings.maturityTexts.loadError')"
    @retry="texts.load"
  >
    <div class="maturity-texts" data-testid="maturity-texts">
      <section class="maturity-texts__group" aria-labelledby="maturity-generic-title">
        <h2 id="maturity-generic-title" class="maturity-texts__title">
          {{ t('settings.maturityTexts.genericTitle') }}
        </h2>
        <p class="maturity-texts__lead">{{ t('settings.maturityTexts.genericLead') }}</p>
        <ul class="maturity-texts__list">
          <li
            v-for="row in generic"
            :key="row.id"
            class="maturity-texts__row"
            :data-band="row.band"
          >
            <MaturityBandChip :band="row.band" />
            <div class="maturity-texts__text">
              <p class="maturity-texts__label">{{ t('settings.maturityTexts.arabic') }}</p>
              <p dir="rtl">{{ row.text?.ar || t('settings.maturityTexts.empty') }}</p>
            </div>
            <div class="maturity-texts__text">
              <p class="maturity-texts__label">{{ t('settings.maturityTexts.english') }}</p>
              <p dir="ltr">{{ row.text?.en || t('settings.maturityTexts.empty') }}</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="pen" @click="texts.edit(row)">
              {{ t('actions.edit') }}
            </BaseButton>
          </li>
        </ul>
      </section>

      <section class="maturity-texts__group" aria-labelledby="maturity-editor-title">
        <h2 id="maturity-editor-title" class="maturity-texts__title">
          {{ t('settings.maturityTexts.editorTitle') }}
        </h2>
        <MaturityTextEditor
          v-if="editor"
          :key="`${editor.band}-${editor.mainCriterionCode}`"
          :initial="editor"
          :mains="mains"
          :errors="errors"
          :saving="saving"
          @save="texts.save"
          @cancel="texts.cancel"
        />
        <BaseButton v-else variant="secondary" icon="plus" @click="texts.edit(null)">
          {{ t('settings.maturityTexts.addOverride') }}
        </BaseButton>
      </section>

      <section class="maturity-texts__group" aria-labelledby="maturity-overrides-title">
        <h2 id="maturity-overrides-title" class="maturity-texts__title">
          {{ t('settings.maturityTexts.overridesTitle') }}
        </h2>
        <p class="maturity-texts__lead">{{ t('settings.maturityTexts.overridesLead') }}</p>
        <p v-if="!overrides.length" class="maturity-texts__lead">
          {{ t('settings.maturityTexts.noOverrides') }}
        </p>
        <ul v-else class="maturity-texts__list">
          <li v-for="row in overrides" :key="row.id" class="maturity-texts__row">
            <MaturityBandChip :band="row.band" />
            <p class="maturity-texts__criterion">
              <bdi dir="ltr">{{ row.mainCriterionCode }}</bdi>
              <span v-if="mainName(row.mainCriterionCode)">
                · {{ mainName(row.mainCriterionCode) }}</span
              >
            </p>
            <span v-if="row.inPublishedVersion === false" class="maturity-texts__orphan">
              <AppIcon name="warning-triangle" />
              {{ t('settings.maturityTexts.orphan') }}
            </span>
            <span v-else class="maturity-texts__meta">
              {{
                t('settings.maturityTexts.updated', {
                  date: formatRiyadhDate(row.updatedAt, locale),
                })
              }}
            </span>
            <span class="maturity-texts__actions">
              <BaseButton variant="ghost" size="sm" icon="pen" @click="texts.edit(row)">
                {{ t('actions.edit') }}
              </BaseButton>
              <BaseButton variant="ghost" size="sm" icon="trash" @click="texts.remove(row)">
                {{ t('settings.maturityTexts.delete') }}
              </BaseButton>
            </span>
          </li>
        </ul>
      </section>
    </div>
  </SettingsSection>
</template>
