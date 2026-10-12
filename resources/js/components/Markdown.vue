<!--
  components/Markdown.vue
  ---------------------------------------------------------------------------
  A small, dependency-free Markdown renderer.

  Tutorial content ships as Markdown, and we deliberately avoid pulling in
  markdown-it + a sanitiser: the subset we need is narrow (headings, emphasis,
  lists, tables, blockquotes, code, links, rules), and building the VNodes
  directly means **no `v-html` anywhere** — admin-authored lesson text can never
  inject markup into a student's page.

  Supported:
    ## / ### / ####   headings
    **bold**  *italic*  `code`
    - / *             unordered lists
    1.                ordered lists
    >                 blockquotes
    ```               fenced code blocks
    | a | b |         tables with a |---|---| separator row
    ---               horizontal rules
    [text](url)       links
-->
<script>
import { defineComponent, h, computed, useSlots } from 'vue';

/* ==========================================================================
   Inline parsing
   ========================================================================== */

const INLINE_RE = /(`[^`]+`)|(\*\*[^*]+\*\*)|(\*[^*\n]+\*)|(\[[^\]]+\]\([^)\s]+\))/g;

/** Parse inline emphasis / code / links into VNodes. */
function parseInline(text, keyPrefix = 'i') {
    if (!text) return null;

    const nodes = [];
    let last = 0;
    let match;
    let n = 0;

    INLINE_RE.lastIndex = 0;
    while ((match = INLINE_RE.exec(text)) !== null) {
        if (match.index > last) nodes.push(text.slice(last, match.index));

        const token = match[0];
        const key = `${keyPrefix}-${n}`;
        n += 1;

        if (token.startsWith('`')) {
            nodes.push(
                h(
                    'code',
                    { key, class: 'rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[0.85em] text-pink-700' },
                    token.slice(1, -1)
                )
            );
        } else if (token.startsWith('**')) {
            nodes.push(h('strong', { key, class: 'font-semibold text-slate-900' }, token.slice(2, -2)));
        } else if (token.startsWith('*')) {
            nodes.push(h('em', { key, class: 'italic' }, token.slice(1, -1)));
        } else {
            const link = token.match(/^\[([^\]]+)\]\(([^)\s]+)\)$/);
            if (link) {
                const href = link[2];
                const external = /^https?:\/\//i.test(href);
                const attrs = {
                    key,
                    href,
                    class:
                        'font-medium text-primary-600 underline decoration-primary-300 underline-offset-2 hover:text-primary-700',
                };
                if (external) {
                    attrs.target = '_blank';
                    attrs.rel = 'noopener noreferrer';
                }
                nodes.push(h('a', attrs, link[1]));
            } else {
                nodes.push(token);
            }
        }

        last = match.index + token.length;
    }

    if (last < text.length) nodes.push(text.slice(last));
    return nodes;
}

/* ==========================================================================
   Block parsing
   ========================================================================== */

const isBlank = (l) => !l.trim();
const HEADING = /^(#{1,4})\s+(.*)$/;
const UL = /^\s*[-*+]\s+(.*)$/;
const OL = /^\s*\d+[.)]\s+(.*)$/;
const QUOTE = /^>\s?(.*)$/;
const HR = /^\s*(?:---|\*\*\*|___)\s*$/;
const FENCE = /^\s*```/;
const TABLE_SEP = /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/;

/** Split a table row on pipes, trimming the optional outer pipes. */
function splitRow(line) {
    let s = line.trim();
    if (s.startsWith('|')) s = s.slice(1);
    if (s.endsWith('|')) s = s.slice(0, -1);
    return s.split('|').map((c) => c.trim());
}

/** Would this line begin a new block? Used to terminate paragraph runs. */
function isBlockStart(line, next) {
    if (HEADING.test(line) || HR.test(line) || FENCE.test(line) || QUOTE.test(line)) return true;
    if (UL.test(line) || OL.test(line)) return true;
    if (line.includes('|') && next !== undefined && TABLE_SEP.test(next)) return true;
    return false;
}

/** Turn a raw Markdown string into an array of block descriptors. */
export function parseBlocks(src) {
    const lines = String(src || '')
        .replace(/\r\n?/g, '\n')
        .split('\n');
    const blocks = [];
    let i = 0;

    while (i < lines.length) {
        const line = lines[i];

        // ---- fenced code ----
        if (FENCE.test(line)) {
            const lang = line.trim().replace(/^```/, '').trim();
            const body = [];
            i += 1;
            while (i < lines.length && !FENCE.test(lines[i])) {
                body.push(lines[i]);
                i += 1;
            }
            i += 1; // closing fence
            blocks.push({ type: 'code', lang, text: body.join('\n') });
            continue;
        }

        if (isBlank(line)) {
            i += 1;
            continue;
        }

        if (HR.test(line)) {
            blocks.push({ type: 'hr' });
            i += 1;
            continue;
        }

        // ---- heading ----
        const heading = line.match(HEADING);
        if (heading) {
            blocks.push({ type: 'heading', level: heading[1].length, text: heading[2].trim() });
            i += 1;
            continue;
        }

        // ---- table ----
        if (line.includes('|') && i + 1 < lines.length && TABLE_SEP.test(lines[i + 1])) {
            const head = splitRow(line);
            const rows = [];
            i += 2;
            while (i < lines.length && lines[i].includes('|') && !isBlank(lines[i])) {
                rows.push(splitRow(lines[i]));
                i += 1;
            }
            blocks.push({ type: 'table', head, rows });
            continue;
        }

        // ---- blockquote (consecutive > lines merge) ----
        if (QUOTE.test(line)) {
            const body = [];
            while (i < lines.length && QUOTE.test(lines[i])) {
                body.push(lines[i].match(QUOTE)[1]);
                i += 1;
            }
            blocks.push({ type: 'quote', text: body.join(' ').trim() });
            continue;
        }

        // ---- lists ----
        if (UL.test(line) || OL.test(line)) {
            const ordered = OL.test(line);
            const items = [];
            while (i < lines.length) {
                const m = lines[i].match(ordered ? OL : UL);
                if (!m) break;
                items.push(m[1].trim());
                i += 1;
            }
            blocks.push({ type: 'list', ordered, items });
            continue;
        }

        // ---- paragraph (merge consecutive non-blank lines) ----
        const para = [];
        while (i < lines.length && !isBlank(lines[i]) && !isBlockStart(lines[i], lines[i + 1])) {
            para.push(lines[i].trim());
            i += 1;
        }
        if (para.length) blocks.push({ type: 'paragraph', text: para.join(' ') });
        else i += 1; // safety: never loop forever
    }

    return blocks;
}

/* ==========================================================================
   Rendering
   ========================================================================== */

const HEADING_CLASS = {
    1: 'mt-8 mb-3 text-2xl font-bold text-slate-900 first:mt-0',
    2: 'mt-8 mb-3 border-b border-slate-200 pb-2 text-xl font-bold text-slate-900 first:mt-0',
    3: 'mt-6 mb-2 text-base font-bold text-slate-900 first:mt-0',
    4: 'mt-5 mb-2 text-sm font-bold uppercase tracking-wide text-slate-700 first:mt-0',
};

function renderBlock(block, idx) {
    const key = `b-${idx}`;

    switch (block.type) {
        case 'heading': {
            // h1 in Markdown -> h2 in the DOM (the page owns the h1)
            const tag = `h${Math.min(block.level + 1, 6)}`;
            return h(tag, { key, class: HEADING_CLASS[block.level] || HEADING_CLASS[3] }, parseInline(block.text, key));
        }

        case 'code':
            return h(
                'pre',
                { key, class: 'my-4 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs leading-relaxed' },
                h('code', { class: 'font-mono text-slate-800' }, block.text)
            );

        case 'hr':
            return h('hr', { key, class: 'my-7 border-slate-200' });

        case 'quote':
            return h(
                'blockquote',
                { key, class: 'my-4 rounded-r-lg border-l-4 border-primary-400 bg-primary-50/70 py-3 pl-4 pr-3' },
                h('p', { class: 'text-sm leading-relaxed text-slate-700' }, parseInline(block.text, key))
            );

        case 'list':
            if (block.ordered) {
                return h(
                    'ol',
                    { key, class: 'my-4 ml-5 list-decimal space-y-1.5 text-sm leading-relaxed text-slate-700' },
                    block.items.map((it, j) => h('li', { key: `${key}-${j}`, class: 'pl-1' }, parseInline(it, `${key}-${j}`)))
                );
            }
            return h(
                'ul',
                { key, class: 'my-4 space-y-1.5 text-sm leading-relaxed text-slate-700' },
                block.items.map((it, j) =>
                    h('li', { key: `${key}-${j}`, class: 'flex gap-2.5 pl-1' }, [
                        h('span', { 'aria-hidden': 'true', class: 'mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full bg-primary-400' }),
                        h('span', null, parseInline(it, `${key}-${j}`)),
                    ])
                )
            );

        case 'table':
            return h('div', { key, class: 'my-5 overflow-x-auto rounded-xl border border-slate-200' }, [
                h('table', { class: 'w-full border-collapse text-sm' }, [
                    h(
                        'thead',
                        { class: 'bg-slate-50' },
                        h(
                            'tr',
                            null,
                            block.head.map((c, j) =>
                                h(
                                    'th',
                                    {
                                        key: `${key}-h-${j}`,
                                        class:
                                            'border-b border-slate-200 px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wide text-slate-600',
                                    },
                                    parseInline(c, `${key}-h-${j}`)
                                )
                            )
                        )
                    ),
                    h(
                        'tbody',
                        null,
                        block.rows.map((row, r) =>
                            h(
                                'tr',
                                { key: `${key}-r-${r}`, class: 'even:bg-slate-50/60' },
                                block.head.map((_, c) =>
                                    h(
                                        'td',
                                        {
                                            key: `${key}-r-${r}-${c}`,
                                            class: 'border-b border-slate-100 px-3 py-2.5 align-top text-slate-700 last:border-r-0',
                                        },
                                        parseInline(row[c] || '', `${key}-r-${r}-${c}`)
                                    )
                                )
                            )
                        )
                    ),
                ]),
            ]);

        default:
            return h('p', { key, class: 'my-3.5 text-sm leading-relaxed text-slate-700' }, parseInline(block.text, key));
    }
}

export default defineComponent({
    name: 'Markdown',

    props: {
        /** The Markdown source. */
        source: { type: String, default: '' },
        /** Optional wrapper class. */
        class: { type: String, default: '' },
    },

    setup(props) {
        const slots = useSlots();

        // Allow both `<Markdown :source="md" />` and `<Markdown>{{ md }}</Markdown>`.
        const text = computed(() => {
            if (props.source) return props.source;
            const slot = slots.default?.();
            if (!slot?.length) return '';
            // Slotted text arrives as VNode text children.
            return slot.map((n) => (typeof n.children === 'string' ? n.children : '')).join('');
        });

        return () => {
            const blocks = parseBlocks(text.value);
            if (!blocks.length) return null;
            return h('div', { class: props.class }, blocks.map((b, i) => renderBlock(b, i)));
        };
    },
});
</script>
