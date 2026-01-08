# Restaurant POS - WordPress Plugin

A complete, production-ready WordPress plugin that provides a comprehensive Restaurant Point of Sale (POS) system with inventory management, kitchen display, and analytics.

## Features

### 1. Point of Sale (POS) Cashier Screen
- Touch-friendly product grid with images
- Category-based filtering
- Real-time cart management with quantity controls
- Cash payment processing with denomination tracking
- Automatic change calculation
- Receipt generation and printing
- Order completion with inventory auto-deduction

### 2. Kitchen Display System (KDS)
- Real-time order queue display
- Order status management (New → Cooking → Ready → Completed)
- Time elapsed tracking for each order
- Auto-refresh functionality (30 seconds)
- No financial data displayed (kitchen-safe view)
- Filter orders by status

### 3. Product Management
- Full CRUD operations for products
- Product fields: Name, SKU, Category, Selling Price, Image, Description
- Active/Inactive status toggle
- Category management

### 4. Inventory Management
- Real-time stock level tracking
- Cost price management (for profit calculations)
- Stock adjustment with reason tracking
- Stock movement history log
- Automatic inventory deduction on order completion
- Low stock warnings and reports

### 5. Orders Management
- Complete order history
- Filter by date range and status
- Detailed order view with all items and payment information
- Order status tracking through the entire lifecycle

### 6. Analytics & Reporting
- **Sales Summary**: Total sales, order count, average order value
- **Top Products**: By quantity sold and revenue generated
- **Profit Report**: Revenue, COGS, gross profit, and profit margin
- **Low Stock Report**: Products at or below threshold
- Date range filtering for custom reports

### 7. Settings Management
- Restaurant name configuration
- Currency symbol customization
- Low stock threshold setting
- Date format preferences

### 8. User Roles & Permissions
- **Restaurant Admin**: Full access to all features
- **Cashier**: POS screen and orders list only
- **Kitchen Staff**: Kitchen display only
- **Inventory Manager**: Products and inventory pages only

## Installation

### Standard WordPress Installation

1. **Download the Plugin**
   - Download the entire `restaurant-pos` directory as a ZIP file

2. **Upload to WordPress**
   - Go to your WordPress admin panel
   - Navigate to `Plugins → Add New`
   - Click `Upload Plugin`
   - Choose the ZIP file
   - Click `Install Now`

3. **Activate the Plugin**
   - After installation, click `Activate Plugin`
   - The plugin will automatically:
     - Create all necessary database tables
     - Set up user roles and capabilities
     - Insert default settings

### Manual Installation

1. Upload the `restaurant-pos` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the plugin via the 'Restaurant POS' menu in the admin sidebar

## Usage Guide

### Initial Setup

1. **Configure Settings**
   - Go to `Restaurant POS → Settings`
   - Set your restaurant name
   - Configure currency symbol
   - Set low stock threshold

2. **Create Categories**
   - Go to `Restaurant POS → Categories`
   - Add product categories (e.g., Beverages, Main Course, Desserts)

3. **Add Products**
   - Go to `Restaurant POS → Products`
   - Add products with prices and assign categories
   - Optionally add SKU, images, and descriptions

4. **Set Inventory Levels**
   - Go to `Restaurant POS → Inventory`
   - Set initial stock quantities for each product
   - Set cost prices for profit tracking

### Daily Operations

#### For Cashiers

1. **Access POS Screen**
   - Go to `Restaurant POS → POS Screen`
   
2. **Create an Order**
   - Click on products to add them to cart
   - Adjust quantities as needed
   - Apply discounts if applicable
   - Enter cash received
   - Click "Complete Order"
   
3. **Print Receipt**
   - After order completion, a receipt is displayed
   - Click "Print Receipt" to print
   - Click "New Order" to start fresh

#### For Kitchen Staff

1. **Access Kitchen Display**
   - Go to `Restaurant POS → Kitchen Display`
   
2. **Manage Orders**
   - View incoming orders in real-time
   - Click "Start Cooking" when you begin preparing
   - Click "Mark Ready" when order is ready for pickup
   - Click "Complete" when order is served/delivered

#### For Inventory Managers

1. **Manage Stock**
   - Go to `Restaurant POS → Inventory`
   - Click "Adjust Stock" to increase/decrease quantities
   - Provide reason for adjustment
   - Stock is automatically deducted when orders are completed

2. **Update Cost Prices**
   - Click "Update Cost" to set/change product cost prices
   - This affects profit calculations in reports

#### For Administrators

1. **View Reports**
   - Go to `Restaurant POS → Reports`
   - Select date range
   - View sales summary, top products, and profit reports
   - Check low stock items

2. **Manage Orders**
   - Go to `Restaurant POS → Orders`
   - Filter by status and date range
   - Click "View Details" to see complete order information

### User Management

#### Creating User Accounts

1. Go to `Users → Add New` in WordPress
2. Fill in user details
3. Select one of the Restaurant POS roles:
   - **Restaurant Admin** - Full access
   - **Cashier** - POS and orders only
   - **Kitchen Staff** - Kitchen display only
   - **Inventory Manager** - Products and inventory only

## Technical Details

### Database Tables

The plugin creates the following tables with your WordPress database prefix:

- `{prefix}rpos_products` - Product catalog
- `{prefix}rpos_categories` - Product categories
- `{prefix}rpos_inventory` - Stock levels and cost prices
- `{prefix}rpos_stock_movements` - Stock change history
- `{prefix}rpos_orders` - Order headers
- `{prefix}rpos_order_items` - Order line items
- `{prefix}rpos_settings` - Plugin settings

### REST API Endpoints

The plugin provides REST API endpoints at `wp-json/restaurant-pos/v1/`:

- `GET/POST /products` - Products management
- `GET/PUT/DELETE /products/{id}` - Single product operations
- `GET /categories` - Get categories
- `GET/POST /orders` - Orders management
- `GET/PUT /orders/{id}` - Single order operations
- `POST /inventory/adjust` - Adjust inventory

### Security Features

- WordPress nonce verification for all forms
- Capability checks for all operations
- Prepared SQL statements to prevent injection
- Input sanitization and validation
- REST API authentication via WordPress nonces

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Modern web browser with JavaScript enabled

## Browser Compatibility

- Chrome (recommended)
- Firefox
- Safari
- Edge

## Troubleshooting

### Plugin doesn't activate
- Check PHP version (minimum 7.4)
- Check WordPress version (minimum 5.8)
- Check file permissions

### Products not showing in POS
- Ensure products are marked as "Active"
- Check that products have a selling price set
- Clear browser cache

### Inventory not deducting
- Ensure order status is set to "Completed"
- Check that products have inventory records
- Review stock movement logs

### Reports showing no data
- Ensure orders exist in the selected date range
- Check that orders have status "Completed"
- For profit reports, ensure cost prices are set

## Support

For issues, questions, or feature requests, please visit:
- GitHub Repository: https://github.com/JafakashGujjar/gpt-pos

## Version History

### Version 1.0.0
- Initial release
- Complete POS functionality
- Kitchen Display System
- Inventory Management
- Analytics & Reporting
- User roles and permissions

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed for restaurant businesses to streamline point-of-sale operations and inventory management.