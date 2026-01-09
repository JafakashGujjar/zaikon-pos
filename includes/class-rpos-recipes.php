<?php
/**
 * Product Recipes Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Recipes {
    
    /**
     * Get recipe for a product
     */
    public static function get_by_product($product_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, 
                    r.ingredient_id,
                    r.inventory_item_id,
                    COALESCE(ing.name, p.name) as ingredient_name,
                    COALESCE(ing.id, i.product_id) as ingredient_product_id
             FROM {$wpdb->prefix}rpos_product_recipes r
             LEFT JOIN {$wpdb->prefix}rpos_ingredients ing ON r.ingredient_id = ing.id
             LEFT JOIN {$wpdb->prefix}rpos_inventory i ON r.inventory_item_id = i.id
             LEFT JOIN {$wpdb->prefix}rpos_products p ON i.product_id = p.id
             WHERE r.product_id = %d
             ORDER BY r.id ASC",
            $product_id
        ));
    }
    
    /**
     * Save recipe for a product
     */
    public static function save_recipe($product_id, $recipe_data) {
        global $wpdb;
        
        // First, delete existing recipe entries for this product
        $wpdb->delete(
            $wpdb->prefix . 'rpos_product_recipes',
            array('product_id' => $product_id),
            array('%d')
        );
        
        // Insert new recipe entries
        if (!empty($recipe_data) && is_array($recipe_data)) {
            foreach ($recipe_data as $ingredient) {
                if (isset($ingredient['ingredient_id']) && 
                    isset($ingredient['quantity_required']) && 
                    floatval($ingredient['quantity_required']) > 0) {
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'rpos_product_recipes',
                        array(
                            'product_id' => $product_id,
                            'ingredient_id' => absint($ingredient['ingredient_id']),
                            'inventory_item_id' => 0, // Keep for backward compatibility
                            'quantity_required' => floatval($ingredient['quantity_required']),
                            'unit' => isset($ingredient['unit']) ? sanitize_text_field($ingredient['unit']) : ''
                        ),
                        array('%d', '%d', '%d', '%f', '%s')
                    );
                }
            }
        }
        
        return true;
    }
    
    /**
     * Delete recipe for a product
     */
    public static function delete_by_product($product_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_product_recipes',
            array('product_id' => $product_id),
            array('%d')
        );
    }
    
    /**
     * Deduct ingredients for order items
     */
    public static function deduct_ingredients_for_order($order_id, $order_items) {
        if (empty($order_items)) {
            error_log('RPOS: deduct_ingredients_for_order called with empty order_items for order #' . $order_id);
            return;
        }
        
        foreach ($order_items as $item) {
            $product_id = is_object($item) ? $item->product_id : (isset($item['product_id']) ? $item['product_id'] : 0);
            $sold_qty = is_object($item) ? $item->quantity : (isset($item['quantity']) ? $item['quantity'] : 0);
            
            if (empty($product_id) || empty($sold_qty)) {
                error_log('RPOS: Skipping item with invalid product_id or quantity in order #' . $order_id);
                continue;
            }
            
            // Get recipe for this product
            $recipe = self::get_by_product($product_id);
            
            if (empty($recipe)) {
                // No recipe defined for this product - this is normal for products without ingredients
                continue;
            }
            
            foreach ($recipe as $ingredient) {
                // Get ingredient_id - must be a positive integer
                $ingredient_id = null;
                
                if (isset($ingredient->ingredient_id) && intval($ingredient->ingredient_id) > 0) {
                    $ingredient_id = intval($ingredient->ingredient_id);
                }
                
                if ($ingredient_id === null) {
                    // Log this for debugging - recipe exists but ingredient_id is not set
                    $product_name = is_object($item) && isset($item->product_name) ? $item->product_name : 'Product #' . $product_id;
                    error_log('RPOS: Recipe ingredient missing ingredient_id for product "' . $product_name . '" in order #' . $order_id . '. Recipe may need to be re-saved.');
                    continue;
                }
                
                // Verify the ingredient exists in the ingredients table
                $ingredient_record = RPOS_Ingredients::get($ingredient_id);
                if (!$ingredient_record) {
                    error_log('RPOS: Ingredient ID ' . $ingredient_id . ' not found in ingredients table for order #' . $order_id);
                    continue;
                }
                
                // Calculate amount to deduct
                $quantity_required = isset($ingredient->quantity_required) ? floatval($ingredient->quantity_required) : 0;
                if ($quantity_required <= 0) {
                    continue;
                }
                
                $deduct_qty = floatval($sold_qty) * $quantity_required;
                
                // Deduct from ingredients table
                $result = RPOS_Ingredients::adjust_stock(
                    $ingredient_id,
                    -$deduct_qty,
                    'Consumption',
                    $order_id,
                    'Sale from Order #' . $order_id
                );
                
                if ($result === false) {
                    error_log('RPOS: Failed to deduct ingredient ID ' . $ingredient_id . ' for order #' . $order_id);
                }
            }
        }
    }
}
