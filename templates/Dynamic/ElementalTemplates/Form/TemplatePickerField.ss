<div class="template-picker-field" id="$ID" $AttributesHTML>
    <% if $hasTemplates %>
        <div class="template-picker__grid">
            <% loop $Templates %>
                <label class="template-picker__card<% if $IsSelected %> template-picker__card--selected<% end_if %>" 
                       for="{$Up.ID}_template_{$ID}"
                       data-template-id="$ID">
                    <input type="radio" 
                           id="{$Up.ID}_template_{$ID}"
                           name="$Up.Name" 
                           value="$ID"
                           class="template-picker__radio"
                           <% if $IsSelected %>checked<% end_if %> />
                    
                    <div class="template-picker__preview">
                        <% if $HasThumbnail %>
                            <img src="$ThumbnailURL" 
                                 alt="$Title preview" 
                                 class="template-picker__thumbnail" 
                                 loading="lazy" />
                        <% else %>
                            <div class="template-picker__placeholder">
                                <span class="font-icon-block-layout"></span>
                            </div>
                        <% end_if %>
                    </div>
                    
                    <div class="template-picker__info">
                        <span class="template-picker__title">$Title</span>
                        <span class="template-picker__meta">$ElementCount block<% if $ElementCount != 1 %>s<% end_if %></span>
                    </div>
                    
                    <div class="template-picker__actions">
                        <a href="$PreviewLink" 
                           target="_blank" 
                           class="template-picker__preview-link"
                           title="Preview template in new window"
                           onclick="event.stopPropagation();">
                            <span class="font-icon-eye"></span>
                        </a>
                    </div>
                </label>
            <% end_loop %>
        </div>
    <% else %>
        <div class="template-picker__empty">
            <span class="font-icon-block-layout template-picker__empty-icon"></span>
            <p>No templates available.</p>
            <p class="template-picker__empty-hint">Create templates in the <a href="admin/elemental-templates">Element Templates</a> section.</p>
        </div>
    <% end_if %>
</div>
