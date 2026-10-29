<%-- Every block's root element. <elemental-flavour> is a custom element
     (client/src/js/elemental-flavour.js) that compares its background with the
     blocks either side and adds collapsed-top, collapsed-bottom and transparent,
     so a theme can halve the spacing between two blocks of the same colour.

     Add classes and attributes through the updateHolderClasses and
     updateHolderAttributes hooks (Toast\Flavours\Extensions\ElementHolderExtension)
     rather than by overriding this template. --%>
<elemental-flavour id="$Anchor" class="$HolderClasses"$HolderAttributes>
    $Element
</elemental-flavour>
