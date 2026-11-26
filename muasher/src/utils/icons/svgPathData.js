const PATH_ELEMENT = /<path\b[^>]*?\sd="([^"]+)"[^>]*>/g

export function svgPathData(source) {
  return [...String(source).matchAll(PATH_ELEMENT)].map((match) => match[1])
}
