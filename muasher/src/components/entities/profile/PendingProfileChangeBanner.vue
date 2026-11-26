<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProfileChangeValue from '@/components/entities/changes/ProfileChangeValue.vue'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  pendingChange: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['cancel'])
const { t, locale } = useI18n()
const expanded = ref(false)
const changes = computed(() => props.pendingChange?.changes ?? [])
</script>

<template>
  <section class="pending-change" role="status" data-testid="pending-profile-change">
    <div class="pending-change__head">
      <AppIcon name="clock" class="pending-change__icon" />
      <div class="pending-change__text">
        <h2 class="pending-change__title">{{ t('portal.profile.pendingChange.title') }}</h2>
        <p class="pending-change__note">{{ t('portal.profile.pendingChange.text') }}</p>
        <p v-if="!pendingChange" class="pending-change__note pending-change__note--muted">
          {{ t('portal.profile.pendingChange.loadFailed') }}
        </p>
      </div>
      <div class="pending-change__actions">
        <BaseButton
          v-if="changes.length"
          variant="secondary"
          :aria-expanded="expanded"
          @click="expanded = !expanded"
        >
          {{ t(`portal.profile.pendingChange.${expanded ? 'hide' : 'show'}`) }}
        </BaseButton>
        <BaseButton v-if="canEdit" variant="ghost" :loading="busy" @click="emit('cancel')">
          {{ t('portal.profile.pendingChange.cancel') }}
        </BaseButton>
      </div>
    </div>
    <ul v-if="expanded" class="pending-change__list">
      <li v-for="change in changes" :key="change.item" class="pending-change__row">
        <span class="pending-change__label">{{ localizedText(change.label, locale) }}</span>
        <span class="pending-change__values">
          <span class="pending-change__side">
            <span class="pending-change__caption">{{
              t('portal.profile.pendingChange.current')
            }}</span>
            <ProfileChangeValue :change="change" side="old" />
          </span>
          <span class="pending-change__side pending-change__side--new">
            <span class="pending-change__caption">{{
              t('portal.profile.pendingChange.proposed')
            }}</span>
            <ProfileChangeValue :change="change" side="new" />
          </span>
        </span>
      </li>
    </ul>
  </section>
</template>
