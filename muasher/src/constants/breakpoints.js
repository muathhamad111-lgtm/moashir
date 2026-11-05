import breakpoints from '@/styles/tokens/breakpoints.module.scss'

export const TABLET_UP_QUERY = `(min-width: ${breakpoints.md})`

export const PHONE_QUERY = `not all and ${TABLET_UP_QUERY}`
