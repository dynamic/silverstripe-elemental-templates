/**
 * Template Picker Field JavaScript
 * Entwine-based interactions for the visual template picker
 */
(function ($) {
    'use strict';

    $.entwine('ss', function ($) {
        /**
         * Template picker field container
         */
        $('.template-picker-field').entwine({
            onmatch: function () {
                this._super();
                this.initializePicker();
            },

            onunmatch: function () {
                this._super();
            },

            initializePicker: function () {
                // Enable keyboard navigation
                this.setupKeyboardNavigation();
                // Update apply button state
                this.updateApplyButtonState();
            },

            setupKeyboardNavigation: function () {
                var self = this;
                var cards = this.find('.template-picker__card');

                cards.attr('tabindex', '0');

                cards.on('keydown', function (e) {
                    var currentCard = $(this);
                    var allCards = self.find('.template-picker__card');
                    var currentIndex = allCards.index(currentCard);
                    var newIndex = -1;

                    switch (e.key) {
                        case 'ArrowRight':
                        case 'ArrowDown':
                            e.preventDefault();
                            newIndex = Math.min(currentIndex + 1, allCards.length - 1);
                            break;
                        case 'ArrowLeft':
                        case 'ArrowUp':
                            e.preventDefault();
                            newIndex = Math.max(currentIndex - 1, 0);
                            break;
                        case 'Enter':
                        case ' ':
                            e.preventDefault();
                            currentCard.find('.template-picker__radio').prop('checked', true).trigger('change');
                            break;
                    }

                    if (newIndex >= 0 && newIndex !== currentIndex) {
                        allCards.eq(newIndex).focus();
                    }
                });
            },

            /**
             * Get the currently selected template ID
             */
            getSelectedTemplateId: function () {
                var checked = this.find('.template-picker__radio:checked');
                return checked.length ? checked.val() : null;
            },

            /**
             * Update the Apply button enabled state based on selection
             */
            updateApplyButtonState: function () {
                var hasSelection = this.getSelectedTemplateId() !== null;
                var applyBtn = this.find('.template-picker__apply-btn');
                applyBtn.prop('disabled', !hasSelection);
            },

            /**
             * Show status message
             */
            showStatus: function (message, type) {
                var statusEl = this.find('.template-picker__status');
                statusEl.removeClass('template-picker__status--success template-picker__status--error template-picker__status--loading');
                statusEl.addClass('template-picker__status--' + type);
                statusEl.text(message);
            },

            /**
             * Clear status message
             */
            clearStatus: function () {
                var statusEl = this.find('.template-picker__status');
                statusEl.removeClass('template-picker__status--success template-picker__status--error template-picker__status--loading');
                statusEl.text('');
            }
        });

        /**
         * Template card selection behavior
         */
        $('.template-picker__card').entwine({
            onclick: function (e) {
                // Don't trigger selection if clicking the preview link
                if ($(e.target).closest('.template-picker__preview-link').length) {
                    return;
                }

                this._super();

                // Update visual state
                this.closest('.template-picker-field')
                    .find('.template-picker__card')
                    .removeClass('template-picker__card--selected');

                this.addClass('template-picker__card--selected');

                // Ensure the radio is checked
                this.find('.template-picker__radio').prop('checked', true);

                // Update apply button state
                this.closest('.template-picker-field').updateApplyButtonState();
            }
        });

        /**
         * Radio input change handler
         */
        $('.template-picker__radio').entwine({
            onchange: function () {
                this._super();

                var picker = this.closest('.template-picker-field');
                var selectedCard = this.closest('.template-picker__card');

                // Update visual states
                picker.find('.template-picker__card').removeClass('template-picker__card--selected');
                selectedCard.addClass('template-picker__card--selected');

                // Update apply button state
                picker.updateApplyButtonState();

                // Trigger custom event for form integration
                picker.trigger('templateselected', [this.val()]);
            }
        });

        /**
         * Apply Template button handler
         * Triggers the CMS Actions "Apply Blocks Template" button by clicking it
         */
        $('.template-picker__apply-btn').entwine({
            onclick: function (e) {
                e.preventDefault();

                var picker = this.closest('.template-picker-field');
                var templateId = picker.getSelectedTemplateId();

                if (!templateId) {
                    picker.showStatus('Please select a template first.', 'error');
                    return;
                }

                // Show loading state
                this.prop('disabled', true).addClass('loading');
                this.text('Applying...');
                picker.showStatus('Applying template...', 'loading');

                // Find and trigger the CMS Actions "Apply Blocks Template" button
                // This button is created by lekoala/silverstripe-cms-actions and handles form submission
                var cmsActionBtn = $('#Form_EditForm_action_doCustomAction_ApplyTemplate');

                if (cmsActionBtn.length) {
                    // Click the CMS action button - it will handle form submission and refresh
                    cmsActionBtn.trigger('click');
                } else {
                    // Button not found - show error
                    picker.showStatus('Apply action not available. Use "More Options → Apply Blocks Template" instead.', 'error');
                    this.prop('disabled', false).removeClass('loading');
                    this.text('Apply Template to Page');
                }
            }
        });

        /**
         * Preview link handler - opens template preview in an iframe modal
         */
        $('.template-picker__preview-link').entwine({
            onclick: function (e) {
                e.preventDefault();
                e.stopPropagation();

                var previewUrl = this.attr('href');
                var templateTitle = this.closest('.template-picker__card').find('.template-picker__title').text();

                // Create modal overlay
                var modal = $('<div class="template-preview-modal" role="dialog" aria-modal="true" aria-label="Template Preview"></div>');
                var modalContent = $('<div class="template-preview-modal__content"></div>');
                var modalHeader = $('<div class="template-preview-modal__header"></div>');
                var modalTitle = $('<h3 class="template-preview-modal__title">' + templateTitle + ' Preview</h3>');
                var closeBtn = $('<button type="button" class="template-preview-modal__close" aria-label="Close preview">&times;</button>');
                var openNewTabBtn = $('<a href="' + previewUrl + '" target="_blank" class="template-preview-modal__newtab" title="Open in new tab"><span class="font-icon-external-link"></span></a>');
                var iframe = $('<iframe class="template-preview-modal__iframe" src="' + previewUrl + '" title="Template preview"></iframe>');
                var loader = $('<div class="template-preview-modal__loader"><span class="font-icon-spinner"></span> Loading preview...</div>');

                // Build modal structure
                modalHeader.append(modalTitle).append(openNewTabBtn).append(closeBtn);
                modalContent.append(modalHeader).append(loader).append(iframe);
                modal.append(modalContent);

                // Hide loader when iframe loads
                iframe.on('load', function () {
                    loader.hide();
                    iframe.css('opacity', '1');
                });

                // Close handlers
                closeBtn.on('click', function () {
                    modal.remove();
                });

                modal.on('click', function (evt) {
                    if (evt.target === modal[0]) {
                        modal.remove();
                    }
                });

                // Keyboard close (Escape key)
                $(document).on('keydown.templatePreviewModal', function (evt) {
                    if (evt.key === 'Escape') {
                        modal.remove();
                        $(document).off('keydown.templatePreviewModal');
                    }
                });

                // Append to body and focus
                $('body').append(modal);
                closeBtn.focus();
            }
        });
    });
})(jQuery);

