/**
 * Category Admin JavaScript
 * Handles media uploader and color picker functionality
 */

jQuery(document).ready(function($) {
    // WordPress Media Uploader
    var mediaUploader;
    
    $('#rpos-upload-category-image').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: rposCategoryAdmin.uploadTitle || 'Choose Category Image',
            button: {
                text: rposCategoryAdmin.useImageText || 'Use this image'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#image_url').val(attachment.url);
            $('#rpos-category-image-preview').html('<img src="' + attachment.url + '" alt="Category Image">');
            $('#rpos-remove-category-image').show();
        });
        
        mediaUploader.open();
    });
    
    // Remove image
    $('#rpos-remove-category-image').on('click', function(e) {
        e.preventDefault();
        $('#image_url').val('');
        $('#rpos-category-image-preview').html('<span class="dashicons dashicons-format-image"></span>');
        $(this).hide();
    });
    
    // Color Picker
    if ($.fn.wpColorPicker) {
        $('.rpos-color-picker').wpColorPicker();
    }
    
    // Color Presets
    $('.rpos-color-preset').on('click', function() {
        var color = $(this).data('color');
        $('#bg_color').val(color).trigger('change');
        if ($.fn.wpColorPicker) {
            $('.rpos-color-picker').wpColorPicker('color', color);
        }
    });
});
