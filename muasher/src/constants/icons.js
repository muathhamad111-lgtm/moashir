export const ICON_SIZES = Object.freeze(['sm', 'md', 'lg'])

export const RTL_MIRRORED_ICONS = Object.freeze([
  'arrow-left',
  'arrow-right',
  'chevron-left',
  'chevron-right',
  'chevron-sm-left',
  'chevron-sm-right',
  'send',
  'login',
  'logout',
  'reply',
])

export const ICON_ALIASES = Object.freeze({
  undo: Object.freeze({ glyph: 'refresh', flipped: true, spinning: false }),
  spinner: Object.freeze({ glyph: 'refresh-dots', flipped: false, spinning: true }),
})

export const STORED_ICON_STYLE = 'fa-solid'

export const STORED_ICON_PATTERN =
  /^fa-(solid|regular|light|thin|duotone|brands|sharp-solid|sharp-regular|sharp-light) fa-[a-z0-9]+(-[a-z0-9]+)*$/
