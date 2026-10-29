# Elemental Flavours

Layout variants for [silverstripe-elemental](https://github.com/silverstripe/silverstripe-elemental).

Elemental lets a developer define a block. This module lets a content author
choose how that block looks, from a picker of pictures, without a developer
touching the page.

It is the part of `toastnz/blocks-layouts` that elemental does not already have.
Everything elemental does well — versioning, in-line editing, publishing,
duplicating, history, reordering, search indexing, reports — is left alone.

## What it adds

| | |
|---|---|
| **Layouts** | A block offers a layout for every `<Block>_<Layout>.ss` template it has. Authors pick one from an icon picker; developers add one by adding a file. |
| **Heading and Content** | Every element gets a `Heading` and a `Content` field, because nearly every block needs them. |
| **Page content block** | Positions a page's own content among its blocks, so the page template is one block in the run rather than a fixed slot around them. |
| **Element items** | A base class for the repeated rows inside a block — accordion panels, gallery images, logos — with sorting, permissions and versioning already right. |
| **Type labels** | Each row in the block list says what kind of block it is, so an author doesn't have to read the icon. |
| **`<elemental-flavour>`** | Every block renders in a custom element that halves the spacing between neighbouring blocks of the same colour. |
| **Block navigation** | Previous, next and a jump menu on a block's own edit screen. |
| **Migration** | A task that moves `toastnz/blocks-layouts` content onto elemental blocks. |

## Requirements

See `composer.json`. Silverstripe CMS 6, elemental 6.2.

## Installation

```bash
composer require toastnz/elemental-flavours
vendor/bin/sake db:build --flush
```

Elemental 6 does not add itself to any page type, so give the ones that should
have blocks elemental's extension as usual:

```yml
Page:
  extensions:
    - DNADesign\Elemental\Extensions\ElementalPageExtension
```

## Layouts

### Adding one

Add a template. A block at `Toast\Elements\ImageElement` renders
`app/templates/Toast/Elements/ImageElement.ss` by default; adding

```
app/templates/Toast/Elements/ImageElement_TextLeft.ss
app/templates/Toast/Elements/ImageElement_Hero.ss
```

gives authors a choice of two layouts named `TextLeft` and `Hero`.

Nothing else is required — no YAML, no PHP, no database field. This is
elemental's own `Style` field and its own template resolution
(`BaseElement::getRenderTemplates()`); the module finds the templates, presents
them, and keeps the stored value honest.

Templates are searched across the whole theme cascade, and for the block's
parent classes as well as itself, so a layout declared once on a base block is
offered by every block that extends it.

> Layout discovery is cached. Run with `?flush=1`, or `sake --flush`, after
> adding a template — the same as adding any other template to a Silverstripe
> site.

### Labelling one

The name is used as the label unless you say otherwise, and the picker falls
back to a plain list when the layouts have no icons.

```yml
Toast\Elements\ImageElement:
  layouts:
    Default:
      label: 'Default'
      icon_class: 'layout-icons-image-large-left'
    TextLeft:
      label: 'Text on the left'
      icon_class: 'layout-icons-image-split-right'
    Hero:
      icon: 'app/client/images/layout-icons/hero.svg'
    Wide:
      disabled: true
  exclude_layouts:
    - Legacy
```

| Key | Type | Meaning |
|---|---|---|
| `label` | string | Shown under the icon. Defaults to the layout name, spaced out. |
| `icon` | string | Path to an SVG, inlined into the picker. Resolved like any module resource, so `'vendor/module: client/images/x.svg'` works too. |
| `icon_class` | string | CSS class for an icon font. The icon font shipped here provides `layout-icons-*`; see `client/src/styles/_layout-icons.scss` for the full list. |
| `disabled` | bool | Hide this layout. |

`exclude_layouts` hides layouts without needing an entry each. An explicit
`disabled` wins over it in both directions, so one layout can be brought back
after a broad exclusion.

YAML cannot invent a layout that has no template — except where you are already
using elemental's own `styles` config, which is still read and still works. A
style with no template is a CSS-only variant, which is what upstream intends.
An entry under `layouts` with no template behind it is ignored, so labels and
icons can be configured before the templates that use them are written.

### Ordering, and the default

The layout named `Default` comes first, then anything named in `layouts`, in the
order you named it, then everything else alphabetically. A block with fewer than
two layouts shows no picker at all.

`Default` is also what a block falls back to. If a template is deleted, blocks
still set to it fall back on their next save, and the CMS tells the author
plainly that the layout has gone and to pick another.

### How it is drawn

Two ways, and which one an author sees depends on where the block is opened.

A block opened on its own screen - which is every block with
`inline_editable: false` - gets a row of tiles: each layout's icon in a square,
its name underneath, the chosen one filled in the CMS accent colour. Every
layout is on screen at once, so the choice is made by looking.

A block edited inline gets the `LayoutPickerField` React component instead, a
dropdown built to sit beside elemental's own add-block popover, because a row of
tiles is more than an inline form has room for.

Both read the same options and write the same field. The tiles are the plain
form markup, so they also stand in if the CMS javascript ever fails to load.

### On the page

The chosen layout is added to the element holder as a class, kebab-cased:
`TextLeft` becomes `.text-left`. This is elemental's `$StyleVariant`.

## Stylesheets and scripts

This module requires nothing on your behalf. Ask for what a template needs, in
the template that needs it:

```html
<% require css("app/client/dist/styles/blocks/text-left/image-block.css") %>
<% require javascript("app/client/dist/scripts/blocks/image-block.js") %>
```

A block that is not on the page then costs nothing, the dependency is written
next to the markup that has it, and whatever your project does with
`Requirements` — combining, deferring, inlining — keeps working.

Ask once, though. If a block's stylesheet already `@use`s its item's
stylesheet, the item template must not ask for it again: the rules are already
on the page, and the only thing a second `<% require %>` adds is another
request.

## Page content block

Apply the extension to any page type whose own content should be movable among
its blocks:

```yml
Toast\Pages\HomePage:
  extensions:
    - Toast\Flavours\Extensions\PageContentExtension
```

Every page of that type gets one page content block, kept in step on save and
reconciled across the whole site on `dev/build`. Removing the extension and
running `dev/build` removes them again. Authors can drag it up and down but
cannot add or delete it: a page with two would render its content twice, and a
page with none would lose its content entirely.

The block renders the page's `Layout` template through the page's own
controller, so each page type keeps one template in the place Silverstripe
already looks for it.

> The elemental area must be rendered from the **page** template, not from the
> `Layout` template. If the `Layout` template renders `$ElementalArea`, then the
> block renders the template that renders the block. The block detects this and
> refuses rather than exhausting memory, and says so in dev.

For a page with more than one elemental area, name the one that holds the
content:

```yml
Toast\Pages\HomePage:
  page_content_area: 'MainElements'
```

## Element items

For the repeated rows inside a block:

```php
class AccordionItem extends ElementItem
{
    private static $table_name = 'AccordionItem';

    private static $has_one = ['Parent' => AccordionElement::class];

    private static $db = ['Title' => 'Varchar(255)', 'Content' => 'HTMLText'];
}
```

```php
class AccordionElement extends BaseElement
{
    private static $has_many = ['Items' => AccordionItem::class];

    // Required, see below.
    private static $inline_editable = false;

    // Without $owns, publishing the block does not publish its rows, and an
    // author publishes a block and sees none of their changes.
    private static $owns = ['Items'];
    private static $cascade_deletes = ['Items'];
    private static $cascade_duplicates = ['Items'];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab('Root.Main', GridField::create(
            'Items',
            'Items',
            $this->Items(),
            ElementItemsConfig::create()
        ));

        return $fields;
    }
}
```

`ElementItem` gives you sorting, permissions that follow the block, versioning,
and a preview link that points at the row's own anchor. `ElementItemsConfig`
gives the grid orderable rows and a delete that deletes.

### A block with items is not inline editable

`private static $inline_editable = false;` is not optional on a block with a
grid in it, and leaving it out is not a cosmetic problem: the block cannot be
opened at all. Elemental builds its inline form in React, from a schema, and
Silverstripe has no React component for a `GridField` - so the CMS raises

```
Could not find a react component for field "PageElements_144_Items".
```

and the author gets an error page instead of their block.

With the setting, the block stops expanding in the list and clicking it opens
the same form on its own screen, where a grid works normally. Nothing else is
lost: the layout picker, the colour fields and the custom CSS class are all
there, on the same tabs they use inline.

Nothing detects this for you. It could be - a block could be asked for its
fields and inspected for a grid - but every block on the site would have to
build its whole field list on every CMS page load to answer the question, and a
line of config on the handful of blocks that have grids is cheaper and easier to
read than the machinery that would save you writing it.

## Type labels

A row in the block list shows the title an author gave it and the first line of
its content, and between them they often don't say whether it is a text block,
an image block or a slider. The only thing on screen that does is the icon,
which has to be learnt before it helps.

`ElementTypeFlagExtension` puts the block's type beside its title as a status
flag - Silverstripe's own way of labelling a record, which elemental already
renders next to the block's name, so this needs no javascript and no template of
its own. Take the extension out of `_config/flavours.yml` and the labels go.

The label is styled quieter than the draft and modified flags it sits alongside.
That is on purpose: those say something an author may need to act on, this one
says what the block is and will say the same thing for ever, and given equal
weight the one that matters loses.

## Colour palettes

If `toastnz/colourpalettes` is installed, its swatch picker is made to work
inside elemental's in-line editor. Nothing to configure, and nothing to install
if you are not using it — the extension checks for the class and returns.

`ColourPaletteField` draws its swatches from `Field()` and a `.ss` template,
neither of which runs when a form is built as a schema for the React form
builder. Without this, the field falls back to the `OptionsetField` component it
inherits and an author is asked to choose between radio buttons labelled
`bef76d` and `7a9c49`.

`FormField` has no `updateSchema*` hook, so the fields are caught on their way
through `EditFormFactory` — after every extension has finished adding fields,
and only on the form where the problem exists.

## Previewing

An element implements `CMSPreviewable`, and Silverstripe's own preview panel
does the rest: editing a page shows the page, and elemental refreshes it as
blocks change.

`ElementItem` implements the same interface and sets `show_stage_link` and
`show_live_link`, which is the part that is easy to miss. The navigator reads
those off the record's own class before it will offer a draft or published
address, and elemental sets them on the block but nothing sets them on a row. A
row without them still reports itself as previewable and still draws the view
mode dropdowns - it just has no address to put in the panel, so an author
switching to split view gets an empty frame and no reason why.

A row previews at its own anchor rather than its block's, so opening one lands
on that row - the accordion panel being edited, not the top of the accordion.
That only works if the item template renders the anchor:

```html
<div id="$Anchor" class="accordion-item">
```

## Moving between blocks

`ElementalAreaNavigationExtension` puts previous and next arrows on a block's
edit form. Silverstripe builds them for any grid whose detail form is told to
show pagination - they are the same pair an author gets on the rows inside a
block - but elemental switches pagination off and adds no paginator, so its
blocks were the one list in the CMS you could only leave by going back.

That matters more when blocks open on their own screen rather than unfolding in
the list, which is `inline_editable: false` and which a block with a grid in it
has no choice about.

The page size is set well above any real page's worth of blocks. The arrows do
cross a page boundary if they have to, but there is no list being paginated on
screen - the block editor is React and fetches its own - so a second page would
exist only to be stepped over.

Beside them, `ElementJumpMenuExtension` adds a menu of every block on the page:
the arrows step, this jumps. Editing block nineteen and wanting block three is
otherwise a trip back to the list, which is a different screen at a different
scroll position. The toggle says where in the page the open block sits, and each
entry gives a number, a name and a type.

It goes in the form's action bar rather than the form body. A field in the body
would have to be laid out around the tabs and the editor, which means overriding
the CMS's own chrome, and the action bar is the strip the CMS already keeps for
moving around a record. The entries are plain links like the arrows, so the CMS
routes them the same way, unsaved-changes prompt included.

It only appears on a block. A row inside one has the arrows and no menu - the
extension is applied to every detail form in the CMS and checks what it is
looking at.

## `<elemental-flavour>`

Every block is rendered inside an `<elemental-flavour>` element
(`templates/DNADesign/Elemental/Layout/FlavourHolder.ss`, set as elemental's
`controller_template`). It is the block's root: it carries the anchor as its
`id`, and the block's classes and attributes.

It is also a custom element. Its script compares each one's computed background
with its neighbours and adds:

- `collapsed-top` / `collapsed-bottom` when the block above / below has the same
  background
- `transparent` when it has no background of its own (it counts as the page's)

so a theme can halve the padding between two blocks that would otherwise read
as one band with a gap in it. Style the element in the theme - it is a custom
element, so it is `display: inline` until told otherwise:

```scss
elemental-flavour {
  display: block;
  padding-block: var(--block-padding);

  &.collapsed-top { padding-top: calc(var(--block-padding) / 2); }
  &.collapsed-bottom { padding-bottom: calc(var(--block-padding) / 2); }
}
```

The script is added to every front-end page, deferred. It re-checks when blocks
are added or restyled, stylesheets load, or the viewport or colour scheme
changes; `customElements.get('elemental-flavour').refreshAll()` forces it. The
element can be used by hand for any band of a page that should collapse against
the blocks beside it. Ported from `toastnz/blocks-layouts`' `<colour-block>`,
which worked the same way.

To load the script yourself instead:

```yml
Toast\Flavours\Extensions\ElementalFlavourScriptExtension:
  require_script: false
```

### Classes and attributes on the holder

The holder gets elemental's own classes (`element`, the class name, the layout
and `ExtraClass`). Add to them from an extension rather than overriding the
template:

```php
protected function updateHolderClasses(array &$classes): void
{
    $classes[] = 'my-block';
}
```

and attributes from the element itself, or from an extension:

```php
public function provideHolderAttributes(): array
{
    return ['data-scroll-progress' => true, 'style' => '--min-height: 50vh'];
}

protected function updateHolderAttributes(array &$attributes): void
{
    $attributes['data-block-type'] = $this->owner->getType();
}
```

`true` writes a bare attribute; `null`, `false` and `''` write nothing.

## Blocks on their own tab

Elemental puts the block editor on the page's Content tab. To give it a tab of
its own, apply `ElementalAreaTabExtension` after elemental's extension and name
the tab:

```yml
Page:
  extensions:
    - DNADesign\Elemental\Extensions\ElementalPageExtension
    - Toast\Flavours\Extensions\ElementalAreaTabExtension
  elemental_area_tab: 'Root.Blocks'
```

Nothing moves unless a tab is named.

## Checking your layouts

```bash
vendor/bin/sake tasks:flavours-layouts --flush
```

Prints the themes being searched and the layouts each block ended up with —
which is the quickest way to find out why a template has not been picked up.

## Missing block areas

Elemental only creates a record's block area when the record is saved on the
draft stage, which nothing on the command line does - so a page that existed
before elemental was applied to it has no area until someone saves it. The CMS
shows that as a confusing "trying to access a section or object that doesn't
exist".

```bash
vendor/bin/sake tasks:flavours-element-areas            # report
vendor/bin/sake tasks:flavours-element-areas --commit   # create them
```

## Migrating from blocks-layouts

`MigrateBlocksTask` copies `toastnz/blocks-layouts` blocks onto elemental
elements. Install this module alongside blocks-layouts, write the element
classes, then map old classes to new ones:

```yml
Toast\Flavours\Tasks\MigrateBlocksTask:
  block_map:
    Toast\Blocks\TextBlock: Toast\Elements\TextElement
    Toast\Blocks\AccordionBlock: Toast\Elements\AccordionElement
  item_map:
    Toast\Blocks\Items\AccordionItem: Toast\Elements\Items\AccordionItem
  # Only for fields that were renamed on the way across.
  field_map:
    Toast\Blocks\CollectionBlock:
      Type: CollectionType
```

```bash
vendor/bin/sake tasks:flavours-migrate-blocks            # report only
vendor/bin/sake tasks:flavours-migrate-blocks --commit
```

Fields, has_ones, has_many items and many_many relations (with their extra
fields) are copied wherever both sides use the same name. The old block's
layout becomes the element's `Style`. Anything the old block held that has
nowhere to go is listed at the end rather than dropped quietly.

- **Page content.** The old page content block isn't copied - a page type with
  `PageContentExtension` gets its own - but its position is, so the page's
  content stays where the author had it.
- **Shared blocks.** A block that was on several pages becomes a separate copy
  on each, and the report says which ones.
- **Links.** A linkfield `Link` is copied rather than shared, so the element
  owns its own and deleting the old block can't take it away.
- **Publishing.** An element is published only if its block was. A page is
  republished only if it had no unpublished changes of its own; one that did
  is left alone and listed, and its blocks go live the next time it's
  published.
- **Re-running.** Pages that already have elements are skipped, so the task is
  safe to run again. `--force` migrates them anyway and will duplicate.

## Local development

When this module is installed from a composer `path` repository, composer
symlinks it into `vendor/`. `silverstripe/vendor-plugin` resolves that symlink
before working out where to expose `client/dist`, so the assets land under
`public/_resources/modules/…` while Silverstripe asks for them under
`public/_resources/vendor/toastnz/elemental-flavours/…`, and the CMS javascript
silently 404s.

Expose the module's dist from the project instead, through the symlinked path:

```json
"extra": {
    "expose": [
        "vendor/toastnz/elemental-flavours/client/dist"
    ]
}
```

Installing normally, from a git repository, needs none of this.

### Building the assets

```bash
npm install
npm run build    # lint, then a production build into client/dist
npm run watch
```

`client/dist` is committed, so a project installing the module never needs
node.
