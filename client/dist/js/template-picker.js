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

                // Trigger custom event for form integration
                picker.trigger('templateselected', [this.val()]);
            }
        });
    });
})(jQuery);
