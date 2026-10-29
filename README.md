# Elemental Flavours

Extras for [Silverstripe Elemental](https://github.com/silverstripe/silverstripe-elemental)
that let an author choose how a block looks, and make blocks nicer to edit.

Elemental lets a developer define a block. Flavours adds:

- **Layouts**: a picture picker for how a block looks. Add a template, get a layout.
- **Heading and Content** fields on every block.
- **`<elemental-flavour>`**: every block's root element, which collapses the spacing between neighbouring blocks of the same colour.
- **Page content block**: lets the page's own content be dragged among its blocks.
- **Element items**: a base class for the rows inside a block (accordion panels, gallery images).
- **Editing helpers**: a type label on each block, previous / next arrows and a jump menu.
- **Live colour preview**: with [colourpalettes](https://github.com/toastnz/colourpalettes), picking a block's colour shows in the preview as you pick it.
- **A migration task** from `toastnz/blocks-layouts`.

Requires Silverstripe CMS 6 and Elemental 6.2.

## Install

```bash
composer require toastnz/elemental-flavours
vendor/bin/sake db:build --flush
```

Until the module is on Packagist, add its repository to your `composer.json` first:

```json
"repositories": [
    { "type": "vcs", "url": "git@github.com:toastnz/elemental-flavours.git" }
]
```

Elemental 6 doesn't add itself to any page type, so apply it to yours:

```yml
Page:
  extensions:
    - DNADesign\Elemental\Extensions\ElementalPageExtension
```

## Layouts

A block offers a layout for every `<Block>_<Layout>.ss` template it has. To add
one, add a template. Nothing else is needed.

```
app/templates/App/Elements/ImageElement.ss
app/templates/App/Elements/ImageElement_TextLeft.ss     <- a layout called TextLeft
app/templates/App/Elements/ImageElement_Hero.ss         <- a layout called Hero
```

Flush after adding a template (`?flush=1`). A block with fewer than two layouts
shows no picker.

Give a layout a label and an icon (both optional):

```yml
App\Elements\ImageElement:
  layouts:
    Default:
      icon_class: 'layout-icons-image-large-left'
    TextLeft:
      label: 'Text on the left'
      icon: 'app/client/images/layout-icons/text-left.svg'
    Hero:
      disabled: true
  exclude_layouts:
    - Legacy
```

| Key | Meaning |
|---|---|
| `label` | Shown under the icon. Defaults to the layout name. |
| `icon` | Path to an SVG, shown in the picker. |
| `icon_class` | A class from the bundled icon font (see `client/src/styles/_layout-icons.scss`). |
| `disabled` | Hide the layout. |

The layout is stored in elemental's own `Style` field. `Default` comes first and
is what a block falls back to if its layout's template is deleted. To list what
each block offers: `vendor/bin/sake tasks:flavours-layouts --flush`.

## Heading and Content

Every block gets a `Heading` and a `Content` field. Elemental's `Title` is only
the label in the CMS list. Use `$Heading` and `$Content` in templates.

## `<elemental-flavour>`

Every block renders inside an `<elemental-flavour>` element, which carries the
block's anchor as its `id`. A small script, loaded on every page, compares each
one's background with its neighbours and adds `collapsed-top`,
`collapsed-bottom` and `transparent` classes. Style them in your theme:

```scss
elemental-flavour {
  display: block;
  padding-block: var(--block-padding);

  &.collapsed-top { padding-top: calc(var(--block-padding) / 2); }
  &.collapsed-bottom { padding-bottom: calc(var(--block-padding) / 2); }
}
```

Add classes and attributes without overriding the template:

```php
use SilverStripe\Core\Extension;

class MyElementExtension extends Extension
{
    protected function updateHolderClasses(array &$classes): void
    {
        $classes[] = 'my-block';
    }

    protected function updateHolderAttributes(array &$attributes): void
    {
        $attributes['data-block-type'] = $this->owner->getType();
    }
}
```

A block can also define `provideHolderAttributes(): array`. A value of `true`
writes a bare attribute. To load the script yourself:

```yml
Toast\Flavours\Extensions\ElementalFlavourScriptExtension:
  require_script: false
```

## Page content block

Lets the author drag a page's own content up and down among its blocks.

```yml
App\Pages\HomePage:
  extensions:
    - Toast\Flavours\Extensions\PageContentExtension
```

Run `sake db:build --flush`. Every page of that type gets one page content
block. Authors can move it but not add or delete it. Render `$ElementalArea`
from the page template, **not** the `Layout` template, or the block renders
itself. For a page with several areas: `page_content_area: 'MainElements'`.

## Element items

A base class for repeated rows inside a block.

```php
class AccordionItem extends ElementItem
{
    private static $table_name = 'AccordionItem';
    private static $has_one = ['Parent' => AccordionElement::class];
    private static $db = ['Title' => 'Varchar(255)', 'Content' => 'HTMLText'];
}

class AccordionElement extends BaseElement
{
    private static $inline_editable = false; // required, see below
    private static $has_many = ['Items' => AccordionItem::class];
    private static $owns = ['Items'];
    private static $cascade_deletes = ['Items'];
    private static $cascade_duplicates = ['Items'];

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldToTab('Root.Main', GridField::create(
                'Items', 'Items', $this->Items(), ElementItemsConfig::create()
            ));
        });

        return parent::getCMSFields();
    }
}
```

You get sorting, permissions that follow the block, versioning and a preview
link. A block with a grid must set `inline_editable = false`: elemental's inline
editor can't show a `GridField`. To open **every** block on its own screen:

```yml
DNADesign\Elemental\Models\BaseElement:
  inline_editable: false
```

## Editing helpers

- A small type label beside each block's name in the list. Remove
  `ElementTypeFlagExtension` from `_config/flavours.yml` to drop it.
- Previous / next arrows and a "Block 3 of 7" jump menu on a block's own screen.
- With [colourpalettes](https://github.com/toastnz/colourpalettes), its swatches
  work in the inline editor.
- To put the block editor on its own tab:

```yml
Page:
  extensions:
    - DNADesign\Elemental\Extensions\ElementalPageExtension
    - Toast\Flavours\Extensions\ElementalAreaTabExtension
  elemental_area_tab: 'Root.Blocks'
```

## Live colour preview

With colourpalettes installed, picking a block's colour shows in the CMS
preview panel straight away, without saving. Nothing to configure. Everything
for it is in `_config/colourpalettes.yml` behind `Only: classexists`, so without
colourpalettes none of it loads and it is not a composer requirement.

Any `has_one` to a `Colour` on a block is written onto its holder as custom
properties (`--_primary-colour` and its contrast pair), which is what the
preview changes.

## Migrating from blocks-layouts

Map the old classes to your new elements, then run the task. It reports only
until you pass `--commit`.

```yml
Toast\Flavours\Tasks\MigrateBlocksTask:
  block_map:
    Toast\Blocks\TextBlock: App\Elements\TextElement
    Toast\Blocks\AccordionBlock: App\Elements\AccordionElement
  item_map:
    Toast\Blocks\Items\AccordionItem: App\Elements\Items\AccordionItem
  field_map: # only for fields that were renamed
    Toast\Blocks\CollectionBlock:
      Type: CollectionType
```

```bash
vendor/bin/sake tasks:flavours-migrate-blocks            # report only
vendor/bin/sake tasks:flavours-migrate-blocks --commit
```

Fields with the same name are copied, and the old layout becomes the `Style`.
Anything with nowhere to go is listed rather than dropped. Safe to re-run:
pages that already have blocks are skipped. A page with unpublished changes is
not published on the author's behalf. A block shared by several pages becomes a
copy on each.

If pages have no block area (elemental only creates one on a draft save):
`sake tasks:flavours-element-areas --commit`.

## Developing

```bash
npm install
npm run build    # lint, then build into client/dist
npm run watch
```

`client/dist` is committed, so installing the module needs no node.
