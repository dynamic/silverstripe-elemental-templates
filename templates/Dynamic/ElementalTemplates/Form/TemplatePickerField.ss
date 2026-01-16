<div class="template-picker-field" id="$ID" $AttributesHTML data-page-id="$PageID">
    <% if $hasTemplates %>
        <div class="template-picker__layout">
            <%-- Left: Scrollable template list --%>
            <div class="template-picker__grid">
                <% loop $Templates %>
                    <label class="template-picker__card<% if $IsSelected %> template-picker__card--selected<% end_if %>" 
                           for="{$Up.ID}_template_{$ID}"
                           data-template-id="$ID"
                           tabindex="0">
                        <input type="radio" 
                               id="{$Up.ID}_template_{$ID}"
                               name="$Up.Name" 
                               value="$ID"
                               class="template-picker__radio"
                               <% if $IsSelected %>checked<% end_if %> />
                        
                        <%-- Left column: Image preview --%>
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
                        
                        <%-- Right column: Title, Description, Preview, Meta --%>
                        <div class="template-picker__content">
                            <h3 class="template-picker__title">$Title</h3>
                            <% if $Description %>
                                <div class="template-picker__description">$Description.LimitCharacters(120)</div>
                            <% end_if %>
                            <div class="template-picker__footer">
                                <span class="template-picker__meta">$ElementCount block<% if $ElementCount != 1 %>s<% end_if %></span>
                                <a href="$PreviewLink" 
                                   target="_blank" 
                                   class="template-picker__preview-link"
                                   title="Preview template in new window"
                                   onclick="event.stopPropagation();">
                                    <span class="font-icon-eye"></span> Preview
                                </a>
                            </div>
                        </div>
                    </label>
                <% end_loop %>
            </div>
            
            <%-- Right: Sticky sidebar with Apply button --%>
            <div class="template-picker__sidebar">
                <div class="template-picker__actions">
                    <p class="template-picker__hint">
                        <span class="font-icon-info-circled"></span>
                        Select a template from the list, then click Apply to add its blocks to this page.
                    </p>
                    <button type="button" 
                            class="template-picker__apply-btn btn btn-primary font-icon-plus-circled"
                            disabled>
                        Apply Template
                    </button>
                    <span class="template-picker__status"></span>
                </div>
            </div>
        </div>
    <% else %>
        <div class="template-picker__empty">
            <span class="font-icon-block-layout template-picker__empty-icon"></span>
            <p>No templates available.</p>
            <p class="template-picker__empty-hint">Create templates in the <a href="admin/elemental-templates">Element Templates</a> section.</p>
        </div>
    <% end_if %>
</div>
