<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import EntityHeaderFacts from './EntityHeaderFacts.vue'
import { localizedText } from '@/utils/localizedText'
import { ACCOUNT_STATUS_KEYS } from '@/constants/entityProfile'
import { LEVEL_TIERS } from '@/constants/levels'

const props = defineProps({
  profile: { type: Object, required: true },
  tier: { type: String, default: LEVEL_TIERS.NONE },
  certificate: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['change-logo', 'logo-expired'])
const { t, locale } = useI18n()

const name = computed(() => localizedText(props.profile.name, locale.value))
const otherName = computed(() => {
  const other = locale.value === 'ar' ? props.profile.name?.en : props.profile.name?.ar
  return other && other !== name.value ? other : ''
})
const initial = computed(() => Array.from(name.value ?? '')[0] ?? '')
const statusKey = computed(() => ACCOUNT_STATUS_KEYS[props.profile.status] ?? 'active')
</script>

<template>
  <header class="profile-header" data-testid="profile-header">
    <div class="profile-header__inner">
      <div class="profile-header__identity">
        <div class="profile-header__logo-block">
          <span class="profile-header__logo">
            <img
              v-if="profile.logo?.url"
              class="profile-header__logo-image"
              :src="profile.logo.url"
              :alt="t('portal.profile.logo.alt')"
              @error="emit('logo-expired')"
            />
            <span v-else class="profile-header__initial" aria-hidden="true">{{ initial }}</span>
          </span>
          <button
            v-if="canEdit"
            type="button"
            class="profile-header__logo-action"
            data-testid="profile-change-logo"
            @click="emit('change-logo')"
          >
            <AppIcon name="upload" />
            <span>{{ t('portal.profile.logo.change') }}</span>
          </button>
        </div>
        <div class="profile-header__names">
          <h1 class="profile-header__name">{{ name }}</h1>
          <p v-if="otherName" class="profile-header__other-name">
            <bdi>{{ otherName }}</bdi>
          </p>
          <div class="profile-header__badges">
            <LevelBadge v-if="tier !== LEVEL_TIERS.NONE" :tier="tier" size="s" />
            <span
              class="profile-header__status"
              :class="`profile-header__status--${statusKey}`"
              data-testid="profile-account-status"
            >
              <span class="profile-header__status-dot" aria-hidden="true"></span>
              {{ t(`portal.profile.status.${statusKey}`) }}
            </span>
          </div>
        </div>
      </div>
      <EntityHeaderFacts :profile="profile" :certificate="certificate" />
    </div>
  </header>
</template>
