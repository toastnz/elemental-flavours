<%-- The layout picker as a row of tiles.

     This is what an author sees on a block opened on its own screen, which is
     an ordinary Silverstripe form rather than a React one. The LayoutPickerField
     component draws a dropdown instead, but it only mounts inside elemental's
     inline form - and it is this markup that stands in if the module javascript
     ever fails to load, which is why it stays close to the core OptionsetField
     markup rather than inventing its own. --%>
<ul $AttributesHTML>
    <% loop $Options %>
        <li class="flavours-layout-picker__option $Class<% if not $Up.hasIcons %> flavours-layout-picker__option--no-icon<% end_if %>">
            <input id="$ID"
                   class="radio flavours-layout-picker__input"
                   name="$Name"
                   type="radio"
                   role="$Role"
                   value="$Value"
                   <% if $isChecked %>checked<% end_if %>
                   <% if $isDisabled %>disabled<% end_if %>
                   <% if $Up.Required %>required<% end_if %>
            />
            <label class="form-label flavours-layout-picker__label" for="$ID">
                <% if $Up.hasIcons %>
                    <span class="flavours-layout-picker__thumbnail">
                        <% if $Icon %>
                            $Icon
                        <% else_if $IconClass %>
                            <span class="layout-icons $IconClass"></span>
                        <% end_if %>
                    </span>
                <% end_if %>

                <span class="flavours-layout-picker__title">$Title</span>
            </label>
        </li>
    <% end_loop %>
</ul>
