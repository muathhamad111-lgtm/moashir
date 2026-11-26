// Markdown-lite for e-mail bodies, parsed into tokens that the preview renders with plain
// template nodes (never v-html): **bold**, [label](https://…) and "- " list lines.
// Like the server, the template is parsed with its :placeholders in place and `fill` puts the
// values into the token texts afterwards (never into a link address), so a value stays literal.
const INLINE = /(\*\*[^*]+\*\*|\[[^\]]+\]\(https:\/\/[^)\s]+\))/g
const LINK = /^\[([^\]]+)\]\((https:\/\/[^)\s]+)\)$/

function inlineTokens(line, fill) {
  return line
    .split(INLINE)
    .filter(Boolean)
    .map((part) => {
      if (part.startsWith('**') && part.endsWith('**'))
        return { type: 'bold', text: fill(part.slice(2, -2)) }
      const link = part.match(LINK)
      return link
        ? { type: 'link', text: fill(link[1]), href: link[2] }
        : { type: 'text', text: fill(part) }
    })
}

export function mailMarkdownBlocks(source, fill = (text) => text) {
  const blocks = []
  String(source ?? '')
    .split('\n')
    .forEach((raw) => {
      const line = raw.trim()
      if (!line) return
      if (line.startsWith('- ')) {
        const last = blocks.at(-1)
        const item = inlineTokens(line.slice(2), fill)
        if (last?.type === 'list') last.items.push(item)
        else blocks.push({ type: 'list', items: [item] })
        return
      }
      blocks.push({ type: 'paragraph', inlines: inlineTokens(line, fill) })
    })
  return blocks
}
