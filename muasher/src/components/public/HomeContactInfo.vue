<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SOCIAL_PLATFORMS } from '@/constants/socialPlatforms'
import AppIcon from '@/components/ui/AppIcon.vue'
import { hasIcon } from '@/utils/icons/iconRegistry'

const props = defineProps({
  siteSettings: { type: Object, default: null },
  localized: { type: Function, required: true },
})

const { t } = useI18n()

const contact = computed(() => props.siteSettings?.contact ?? null)
const email = computed(() => contact.value?.email ?? '')
const phone = computed(() => contact.value?.phone ?? '')
const address = computed(() =>
  contact.value?.address ? props.localized(contact.value.address) : '',
)
const social = computed(() =>
  (props.siteSettings?.social ?? [])
    .map((link) => ({ ...link, meta: SOCIAL_PLATFORMS[link.platform] }))
    .filter((link) => link.meta && link.url),
)
const phoneHref = computed(() => `tel:${phone.value.replace(/\s+/g, '')}`)
const phoneIconShown = hasIcon('phone')
</script>

<template>
  <ul v-reveal="{ stagger: true }" class="home-contact__info">
    <li v-if="email" class="home-contact__item">
      <span class="home-contact__item-icon" aria-hidden="true"><AppIcon name="envelope" /></span>
      <span class="home-contact__item-body">
        <span class="home-contact__item-label">{{ t('public.home.contact.email') }}</span>
        <a class="home-contact__item-value" :href="`mailto:${email}`"
          ><bdi dir="ltr">{{ email }}</bdi></a
        >
      </span>
    </li>
    <li v-if="phone" class="home-contact__item">
      <span v-if="phoneIconShown" class="home-contact__item-icon" aria-hidden="true"
        ><AppIcon name="phone"
      /></span>
      <span class="home-contact__item-body">
        <span class="home-contact__item-label">{{ t('public.home.contact.phone') }}</span>
        <a class="home-contact__item-value" :href="phoneHref"
          ><bdi dir="ltr">{{ phone }}</bdi></a
        >
      </span>
    </li>
    <li v-if="address" class="home-contact__item">
      <span class="home-contact__item-icon" aria-hidden="true"><AppIcon name="location" /></span>
      <span class="home-contact__item-body">
        <span class="home-contact__item-label">{{ t('public.home.contact.address') }}</span>
        <span class="home-contact__item-value">{{ address }}</span>
      </span>
    </li>
    <li v-if="social.length" class="home-contact__item">
      <span class="home-contact__item-icon" aria-hidden="true"><AppIcon name="globe" /></span>
      <span class="home-contact__item-body">
        <span class="home-contact__item-label">{{ t('public.home.contact.social') }}</span>
        <span class="home-contact__social">
          <a
            v-for="link in social"
            :key="link.platform"
            class="home-contact__social-link"
            :href="link.url"
            target="_blank"
            rel="noopener"
          >
            <AppIcon :name="link.meta.icon" />
            {{ t(link.meta.labelKey) }}
          </a>
        </span>
      </span>
    </li>
  </ul>
</template>
