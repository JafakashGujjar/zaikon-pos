/**
 * Rider Assignment Popup for POS
 * Shows after delivery order completion to assign a rider
 */

(function($) {
    'use strict';
    
    // Initialize rider assignment system
    var RiderAssignment = {
        
        // Show rider assignment popup
        showPopup: function(orderId, orderNumber, deliveryInfo) {
            // Create overlay
            var overlay = $('<div class="rpos-rider-overlay"></div>');
            
            // Create popup
            var popup = $('<div class="rpos-rider-popup"></div>');
            popup.html(this.getPopupHTML(orderId, orderNumber, deliveryInfo));
            
            overlay.append(popup);
            $('body').append(overlay);
            
            // Load active riders
            this.loadRiders(deliveryInfo);
            
            // Bind events
            this.bindEvents();
        },
        
        // Generate popup HTML
        getPopupHTML: function(orderId, orderNumber, deliveryInfo) {
            return `
                <div class="rpos-rider-popup-header">
                    <h2>Assign Rider to Order #${orderNumber}</h2>
                    <button class="rpos-rider-close">&times;</button>
                </div>
                <div class="rpos-rider-popup-body">
                    <div class="rpos-delivery-info">
                        <p><strong>Customer:</strong> ${deliveryInfo.customerName}</p>
                        <p><strong>Phone:</strong> ${deliveryInfo.customerPhone}</p>
                        <p><strong>Location:</strong> ${deliveryInfo.locationName}</p>
                        <p><strong>Distance:</strong> ${deliveryInfo.distanceKm} km</p>
                    </div>
                    
                    <div class="rpos-rider-selection">
                        <h3>Select Rider</h3>
                        <div class="rpos-rider-list">
                            <div class="rpos-loading">Loading riders...</div>
                        </div>
                    </div>
                    
                    <div class="rpos-assignment-actions">
                        <button class="button button-large rpos-skip-assignment">Skip / Assign Later</button>
                        <button class="button button-primary button-large rpos-confirm-assignment" disabled>
                            Confirm Assignment
                        </button>
                    </div>
                </div>
                <input type="hidden" id="rpos-selected-rider-id" value="">
                <input type="hidden" id="rpos-assignment-order-id" value="${orderId}">
            `;
        },
        
        // Load active riders from server
        loadRiders: function(deliveryInfo) {
            var self = this;
            
            $.ajax({
                url: rposAdmin.restUrl + 'riders/active',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposAdmin.restNonce);
                },
                success: function(riders) {
                    self.renderRiders(riders, deliveryInfo.distanceKm);
                },
                error: function() {
                    $('.rpos-rider-list').html('<p class="error">Failed to load riders. Please try again.</p>');
                }
            });
        },
        
        // Render riders list
        renderRiders: function(riders, distanceKm) {
            var html = '';
            
            if (riders.length === 0) {
                html = '<p class="error">No active riders available. Please assign a rider later.</p>';
            } else {
                riders.forEach(function(rider) {
                    var estimatedPayout = calculateEstimatedPayout(rider, distanceKm);
                    var workload = rider.pending_deliveries || 0;
                    var workloadClass = workload > 3 ? 'high' : (workload > 1 ? 'medium' : 'low');
                    
                    html += `
                        <div class="rpos-rider-item" data-rider-id="${rider.id}">
                            <div class="rpos-rider-info">
                                <h4>${rider.name}</h4>
                                <p class="rpos-rider-phone">${rider.phone || 'No phone'}</p>
                                <p class="rpos-rider-workload rpos-workload-${workloadClass}">
                                    Current workload: ${workload} pending deliveries
                                </p>
                                <p class="rpos-rider-payout">
                                    <strong>Estimated payout: Rs ${estimatedPayout.toFixed(2)}</strong>
                                </p>
                            </div>
                            <div class="rpos-rider-select">
                                <button class="button button-primary rpos-select-rider" data-rider-id="${rider.id}">
                                    Select
                                </button>
                            </div>
                        </div>
                    `;
                });
            }
            
            $('.rpos-rider-list').html(html);
        },
        
        // Bind event handlers
        bindEvents: function() {
            var self = this;
            
            // Close popup
            $(document).on('click', '.rpos-rider-close, .rpos-skip-assignment', function() {
                self.closePopup();
            });
            
            // Select rider
            $(document).on('click', '.rpos-select-rider', function() {
                var riderId = $(this).data('rider-id');
                
                // Update UI
                $('.rpos-rider-item').removeClass('selected');
                $(this).closest('.rpos-rider-item').addClass('selected');
                
                // Update hidden field
                $('#rpos-selected-rider-id').val(riderId);
                
                // Enable confirm button
                $('.rpos-confirm-assignment').prop('disabled', false);
            });
            
            // Confirm assignment
            $(document).on('click', '.rpos-confirm-assignment', function() {
                var riderId = $('#rpos-selected-rider-id').val();
                var orderId = $('#rpos-assignment-order-id').val();
                
                if (riderId && orderId) {
                    self.assignRider(orderId, riderId);
                }
            });
        },
        
        // Assign rider to order via API
        assignRider: function(orderId, riderId) {
            var self = this;
            var $button = $('.rpos-confirm-assignment');
            
            $button.prop('disabled', true).text('Assigning...');
            
            $.ajax({
                url: rposAdmin.restUrl + 'assign-rider',
                method: 'POST',
                data: JSON.stringify({
                    order_id: parseInt(orderId),
                    rider_id: parseInt(riderId)
                }),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposAdmin.restNonce);
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccessMessage(response.message);
                        setTimeout(function() {
                            self.closePopup();
                        }, 2000);
                    } else {
                        alert(response.message || 'Failed to assign rider');
                        $button.prop('disabled', false).text('Confirm Assignment');
                    }
                },
                error: function(xhr) {
                    var error = xhr.responseJSON && xhr.responseJSON.message ? 
                                xhr.responseJSON.message : 'Failed to assign rider';
                    alert(error);
                    $button.prop('disabled', false).text('Confirm Assignment');
                }
            });
        },
        
        // Show success message
        showSuccessMessage: function(message) {
            $('.rpos-rider-popup-body').html(`
                <div class="rpos-success-message">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <h3>${message}</h3>
                    <p>Closing...</p>
                </div>
            `);
        },
        
        // Close popup
        closePopup: function() {
            $('.rpos-rider-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        }
    };
    
    // Helper function to calculate estimated payout
    function calculateEstimatedPayout(rider, distanceKm) {
        var payoutType = rider.payout_type || 'per_km';
        var perDeliveryRate = parseFloat(rider.per_delivery_rate) || 0;
        var perKmRate = parseFloat(rider.per_km_rate) || 10;
        var baseRate = parseFloat(rider.base_rate) || 20;
        
        switch (payoutType) {
            case 'per_delivery':
                return perDeliveryRate;
            case 'per_km':
                return baseRate + (distanceKm * perKmRate);
            case 'hybrid':
                return perDeliveryRate + (distanceKm * perKmRate);
            default:
                return baseRate + (distanceKm * perKmRate);
        }
    }
    
    // Expose to global scope
    window.RiderAssignment = RiderAssignment;
    
})(jQuery);
