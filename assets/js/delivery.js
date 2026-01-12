/**
 * Delivery Modal JavaScript
 * Handles delivery order details collection and charge calculation
 */

(function($) {
    'use strict';
    
    var RPOS_Delivery = {
        modal: null,
        overlay: null,
        currentCallback: null,
        deliveryData: null,
        
        init: function() {
            this.createModal();
            this.bindEvents();
        },
        
        createModal: function() {
            // Create modal HTML
            var modalHtml = `
                <div class="rpos-delivery-modal-overlay" id="rpos-delivery-modal-overlay">
                    <div class="rpos-delivery-modal">
                        <div class="rpos-delivery-modal-header">
                            <h2>Delivery Details</h2>
                            <button type="button" class="rpos-delivery-modal-close" id="rpos-delivery-modal-close">&times;</button>
                        </div>
                        <div class="rpos-delivery-modal-body">
                            <form id="rpos-delivery-form">
                                <div class="rpos-delivery-form-group">
                                    <label for="rpos-delivery-area">
                                        Delivery Area <span class="required">*</span>
                                    </label>
                                    <select id="rpos-delivery-area" name="area_id" required>
                                        <option value="">-- Select Area --</option>
                                    </select>
                                    <span class="error-message" id="area-error">Please select a delivery area</span>
                                </div>
                                
                                <div class="rpos-delivery-form-group">
                                    <label for="rpos-customer-name">
                                        Customer Name <span class="required">*</span>
                                    </label>
                                    <input type="text" id="rpos-customer-name" name="customer_name" placeholder="Enter customer name" required>
                                    <span class="error-message" id="name-error">Please enter customer name</span>
                                </div>
                                
                                <div class="rpos-delivery-form-group">
                                    <label for="rpos-customer-phone">
                                        Customer Phone <span class="required">*</span>
                                    </label>
                                    <input type="tel" id="rpos-customer-phone" name="customer_phone" placeholder="Enter phone number" required>
                                    <span class="error-message" id="phone-error">Please enter a valid phone number</span>
                                </div>
                                
                                <div class="rpos-delivery-form-group">
                                    <label for="rpos-special-instructions">
                                        Special Instructions
                                    </label>
                                    <textarea id="rpos-special-instructions" name="special_instructions" rows="3" placeholder="e.g., Call on arrival, Don't ring bell, Extra spicy"></textarea>
                                </div>
                                
                                <div class="rpos-delivery-charge-display" id="rpos-delivery-charge-display" style="display: none;">
                                    <div class="rpos-delivery-charge-row">
                                        <span class="rpos-delivery-charge-label">Subtotal:</span>
                                        <span class="rpos-delivery-charge-value" id="rpos-delivery-subtotal">$0.00</span>
                                    </div>
                                    <div class="rpos-delivery-charge-row">
                                        <span class="rpos-delivery-charge-label">Delivery Charge:</span>
                                        <span class="rpos-delivery-charge-value" id="rpos-delivery-charge-value">$0.00</span>
                                    </div>
                                    <div class="rpos-delivery-charge-row total">
                                        <span class="rpos-delivery-charge-label">Total:</span>
                                        <span class="rpos-delivery-charge-value" id="rpos-delivery-total">$0.00</span>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="rpos-delivery-modal-footer">
                            <button type="button" class="btn-secondary" id="rpos-delivery-cancel">Cancel</button>
                            <button type="button" class="btn-primary" id="rpos-delivery-submit">Confirm Delivery</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Append to body
            $('body').append(modalHtml);
            
            this.modal = $('#rpos-delivery-modal-overlay');
            this.overlay = this.modal;
        },
        
        bindEvents: function() {
            var self = this;
            
            // Close modal
            $('#rpos-delivery-modal-close, #rpos-delivery-cancel').on('click', function() {
                self.close(false);
            });
            
            // Close on overlay click
            this.modal.on('click', function(e) {
                if (e.target === this) {
                    self.close(false);
                }
            });
            
            // Area change - calculate delivery charge
            $('#rpos-delivery-area').on('change', function() {
                self.calculateDeliveryCharge();
            });
            
            // Submit form
            $('#rpos-delivery-submit').on('click', function() {
                self.submit();
            });
            
            // Real-time validation
            $('#rpos-customer-phone').on('input', function() {
                self.validatePhone(this);
            });
        },
        
        open: function(subtotal, callback) {
            var self = this;
            this.currentCallback = callback;
            
            // Load delivery areas
            this.loadDeliveryAreas(function() {
                // Set subtotal
                $('#rpos-delivery-subtotal').text(self.formatCurrency(subtotal));
                
                // Reset form
                $('#rpos-delivery-form')[0].reset();
                $('#rpos-delivery-charge-display').hide();
                $('.error-message').removeClass('visible');
                $('input, select').removeClass('error');
                
                // Show modal
                self.modal.addClass('active');
            });
        },
        
        close: function(confirmed) {
            this.modal.removeClass('active');
            
            if (!confirmed && this.currentCallback) {
                // User cancelled - revert order type
                this.currentCallback(null);
            }
            
            this.currentCallback = null;
            this.deliveryData = null;
        },
        
        loadDeliveryAreas: function(callback) {
            $.ajax({
                url: rposAdmin.restUrl + 'delivery-areas?active_only=true',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposAdmin.restNonce);
                },
                success: function(areas) {
                    var select = $('#rpos-delivery-area');
                    select.find('option:not(:first)').remove();
                    
                    if (areas && areas.length > 0) {
                        areas.forEach(function(area) {
                            // Support both old (distance_value) and new (distance_km) field names
                            var distance = area.distance_km || area.distance_value || 0;
                            select.append($('<option>', {
                                value: area.id,
                                text: area.name + ' (' + distance + ' km)',
                                'data-distance': distance
                            }));
                        });
                    }
                    
                    if (callback) callback();
                },
                error: function() {
                    if (window.ZaikonToast) {
                        window.ZaikonToast.error('Failed to load delivery areas');
                    } else {
                        alert('Failed to load delivery areas');
                    }
                }
            });
        },
        
        calculateDeliveryCharge: function() {
            var self = this;
            var areaId = $('#rpos-delivery-area').val();
            var subtotal = parseFloat($('#rpos-delivery-subtotal').text().replace(/[^0-9.-]+/g, ''));
            
            if (!areaId) {
                $('#rpos-delivery-charge-display').hide();
                return;
            }
            
            // Use new Zaikon API endpoint
            $.ajax({
                url: rposAdmin.restUrl.replace('restaurant-pos/v1/', 'zaikon/v1/') + 'calc-delivery-charges',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    location_id: parseInt(areaId),
                    items_subtotal_rs: subtotal
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposAdmin.restNonce);
                },
                success: function(response) {
                    var deliveryCharge = parseFloat(response.delivery_charges_rs) || 0;
                    var isFree = response.is_free_delivery == 1;
                    var total = subtotal + deliveryCharge;
                    
                    $('#rpos-delivery-charge-value').html(
                        self.formatCurrency(deliveryCharge) + 
                        (isFree ? '<span class="rpos-delivery-free-badge">FREE</span>' : '')
                    );
                    $('#rpos-delivery-total').text(self.formatCurrency(total));
                    $('#rpos-delivery-charge-display').show();
                    
                    // Store for later use
                    self.deliveryData = {
                        delivery_charge: deliveryCharge,
                        is_free: isFree,
                        rule_type: response.rule_type
                    };
                },
                error: function(xhr) {
                    console.error('Delivery charge calculation failed:', xhr);
                    if (window.ZaikonToast) {
                        window.ZaikonToast.error('Failed to calculate delivery charge');
                    }
                }
            });
        },
        
        validatePhone: function(input) {
            var phone = $(input).val();
            var isValid = /^[\d\s\-\+\(\)]+$/.test(phone) && phone.length >= 10;
            
            if (phone && !isValid) {
                $(input).addClass('error');
                $('#phone-error').addClass('visible');
            } else {
                $(input).removeClass('error');
                $('#phone-error').removeClass('visible');
            }
            
            return isValid || !phone;
        },
        
        validate: function() {
            var isValid = true;
            
            // Validate area
            var area = $('#rpos-delivery-area').val();
            if (!area) {
                $('#rpos-delivery-area').addClass('error');
                $('#area-error').addClass('visible');
                isValid = false;
            } else {
                $('#rpos-delivery-area').removeClass('error');
                $('#area-error').removeClass('visible');
            }
            
            // Validate name
            var name = $('#rpos-customer-name').val().trim();
            if (!name) {
                $('#rpos-customer-name').addClass('error');
                $('#name-error').addClass('visible');
                isValid = false;
            } else {
                $('#rpos-customer-name').removeClass('error');
                $('#name-error').removeClass('visible');
            }
            
            // Validate phone
            var phone = $('#rpos-customer-phone').val().trim();
            if (!phone || !this.validatePhone($('#rpos-customer-phone')[0])) {
                $('#rpos-customer-phone').addClass('error');
                $('#phone-error').addClass('visible');
                isValid = false;
            } else {
                $('#rpos-customer-phone').removeClass('error');
                $('#phone-error').removeClass('visible');
            }
            
            return isValid;
        },
        
        submit: function() {
            if (!this.validate()) {
                if (window.ZaikonToast) {
                    window.ZaikonToast.error('Please fill in all required fields');
                }
                return;
            }
            
            var data = {
                area_id: $('#rpos-delivery-area').val(),
                customer_name: $('#rpos-customer-name').val().trim(),
                customer_phone: $('#rpos-customer-phone').val().trim(),
                special_instructions: $('#rpos-special-instructions').val().trim(),
                delivery_charge: this.deliveryData ? this.deliveryData.delivery_charge : 0,
                is_delivery: 1
            };
            
            this.close(true);
            
            if (this.currentCallback) {
                this.currentCallback(data);
            }
        },
        
        formatCurrency: function(amount) {
            // Get currency symbol from global settings or default to $
            var currency = (typeof rposAdmin !== 'undefined' && rposAdmin.currencySymbol) 
                ? rposAdmin.currencySymbol 
                : '$';
            return currency + parseFloat(amount).toFixed(2);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        RPOS_Delivery.init();
    });
    
    // Make available globally
    window.RPOS_Delivery = RPOS_Delivery;
    
})(jQuery);
