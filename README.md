# Contact Form 7 Product orders extension

Transform Contact Form 7 into a product ordering system. This extension adds multi-product order capabilities, validation, database storage, and formatted email notifications to Contact Form 7.

![cf7-place-order.jpg](img%2Fcf7-place-order.jpg)

## Features

- 🛍️ Multiple products per order with dynamic field addition
- 🔄 Dynamic form fields with JavaScript
- 💾 Database storage for orders
- ✅ Built-in quantity validation
- 📧 Professional HTML email notifications

## Installation

1. Download the plugin zip file
2. Go to WordPress admin > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Activate the plugin
5. Ensure Contact Form 7 is installed and activated

## Quick Start

1. Create a new Contact Form 7 form
2. Add this basic product order structure to your form:

```html
<div class="customer-info">
    <label>Your Name (required)
        [text* your-name] </label>

    <label>Your Email (required)
        [email* your-email] </label>
</div>

<div class="product-list">
    <h3>Product Orders</h3>
    
    <div class="product-entries">
        <div class="product-entry">
            <label>Product Name
                [text* product-name class:product-name] </label>
                
            <label>Quantity
                [number* product-quantity min:1 max:100] </label>
                
            <label>Special Notes
                [textarea product-notes] </label>
        </div>
    </div>

    <button type="button" class="add-product button">Add Another Product</button>
</div>

[submit "Place Order"]
```

3. Configure the email template in the form's Mail tab:

```html
Dear Admin,

A new product order has been received.

Customer Details:
Name: [your-name]
Email: [your-email]

[product-table]

Best regards,
Your Website
```
![cf7-order-form-email.jpg](img%2Fcf7-order-form-email.jpg)

 
---

### Database Structure


```
CREATE TABLE wp_cf7_orders (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    customer_email varchar(100) NOT NULL,
    customer_name varchar(100) NOT NULL,
    products longtext NOT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id)
)
```
