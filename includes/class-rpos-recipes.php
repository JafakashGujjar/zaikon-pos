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
        foreach ($order_items as $item) {
            $product_id = is_object($item) ? $item->product_id : $item['product_id'];
            $sold_qty = is_object($item) ? $item->quantity : $item['quantity'];
            
            // Get recipe for this product
            $recipe = self::get_by_product($product_id);
            
            if (!empty($recipe)) {
                foreach ($recipe as $ingredient) {
                    // Use ingredient_id if available, otherwise fall back to inventory_item_id
                    $ingredient_id = isset($ingredient->ingredient_id) ? $ingredient->ingredient_id : null;
                    
                    if (!$ingredient_id) {
                        // Skip if no ingredient_id (old recipes not yet migrated or incomplete data)
                        continue;
                    }
                    
                    // Calculate amount to deduct
                    $deduct_qty = floatval($sold_qty) * floatval($ingredient->quantity_required);
                    
                    // Deduct from ingredients table
                    RPOS_Ingredients::adjust_stock(
                        $ingredient_id,
                        -$deduct_qty,
                        'Consumption',
                        $order_id,
                        'Sale from Order #' . $order_id
                    );
                }
            }
        }
    }
}
