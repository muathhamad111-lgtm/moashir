const path = (d) => ({ tag: 'path', attrs: { d } })
const circle = (cx, cy, r) => ({ tag: 'circle', attrs: { cx, cy, r } })

export const LANDING_GLYPHS = Object.freeze({
  people: [
    circle(16, 10, 4),
    path('M8 26v-2a8 8 0 0 1 16 0v2'),
    circle(7, 13, 3),
    path('M2 24v-1a5 5 0 0 1 6-5'),
    circle(25, 13, 3),
    path('M30 24v-1a5 5 0 0 0-6-5'),
  ],
  institution: [
    path('M4 12 16 5l12 7'),
    path('M6 13v11M11 13v11M16 13v11M21 13v11M26 13v11'),
    path('M3 27h26'),
  ],
  endowment: [
    path('M16 27V14'),
    path('M16 18c-5 0-8-3-8-8 5 0 8 3 8 8z'),
    path('M16 15c0-5 3-8 8-8 0 5-3 8-8 8z'),
    path('M7 27h18'),
  ],
  gauge: [
    path('M5 22a11 11 0 1 1 22 0'),
    path('M16 22l6-7'),
    circle(16, 22, 1.6),
    path('M5 26h22'),
  ],
  gap_bars: [path('M5 26V12M11 26V6M17 26v-8M23 26V9'), path('M17 14v-4M23 7V5'), path('M3 27h26')],
  medal: [circle(16, 12, 7), path('M11.5 18 9 28l7-3 7 3-2.5-10'), path('M13 12l2 2 4-4')],
  lens: [circle(13, 13, 8), path('M19 19l8 8'), path('M9 13a4 4 0 0 1 4-4')],
  trophy: [
    path('M10 5h12v6a6 6 0 0 1-12 0z'),
    path('M10 7H6v2a4 4 0 0 0 4 4M22 7h4v2a4 4 0 0 1-4 4'),
    path('M16 17v5M11 27h10M12 22h8'),
  ],
  growth: [path('M4 26l8-8 5 5 11-12'), path('M20 11h8v8'), path('M4 29h24')],
  grid: [
    path(
      'M5 5h6v6H5zM13 5h6v6h-6zM21 5h6v6h-6zM5 13h6v6H5zM13 13h6v6h-6zM21 13h6v6h-6zM5 21h6v6H5zM13 21h6v6h-6zM21 21h6v6h-6z',
    ),
  ],
  clipboard: [path('M10 5h12v4H10z'), path('M8 7H6v21h20V7h-2'), path('M11 18l3 3 7-7')],
  shield: [path('M16 3l10 4v7c0 7-4.5 11-10 14-5.5-3-10-7-10-14V7z'), path('M11 16l3.5 3.5L21 13')],
  stairs: [path('M4 26h7v-6h7v-6h7V6'), path('M21 6h4v4')],
  cycle: [path('M16 5a11 11 0 1 0 11 11'), path('M16 10v6l4 3'), path('M22 5h5v5')],
  target: [
    path('M16 4a12 12 0 1 0 0 24 12 12 0 1 0 0-24z'),
    circle(16, 16, 6),
    circle(16, 16, 1.5),
  ],
  document: [path('M9 4h11l5 5v19H9z'), path('M20 4v5h5'), path('M13 15h8M13 19h8M13 23h5')],
  map: [path('M4 8l8-3 8 3 8-3v19l-8 3-8-3-8 3z'), path('M12 5v19M20 8v19')],
})

export const DEFAULT_LANDING_GLYPH = 'grid'

export function landingGlyph(name) {
  return LANDING_GLYPHS[name] ?? LANDING_GLYPHS[DEFAULT_LANDING_GLYPH]
}
