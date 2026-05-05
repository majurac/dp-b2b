=== SilkyPress Input Field Block ===
Contributors: diana_burduja 
Email: diana@burduja.eu
Tags: checkout block editor, checkout block field, checkout block input field, checkout block customizer, WooCommerce checkout manager 
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.7
Requires PHP: 7.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A plugin for adding input fields to the WooCommerce Checkout Block.

== Description ==

**Basically, it is a checkout field editor plugin, but for the WooCommerce block checkout**

[youtube https://youtu.be/kHiKNGsyYCQ]

The WooCommerce block checkout can be edited directly from the Gutenberg editor (open the "WP Admin -> Pages -> Checkout" for editing). By default WooCommerce allows adding only Paragraph/Image/Separator inner blocks to the checkout block. The **SilkyPress Input Field Block** plugin lets you add inner blocks with a custom input field to the checkout block.

The plugin creates a block, called `Input Field`, which can be added to an inner block of the `Checkout Block`. One instance of the block will add one custom field to the checkout form. The block can be added as many times as necessary to the `Checkout Block` and can be inserted more than one time within the same inserter place of the `Checkout Block`.

### Input Field block **settings**

After adding an `Input Field` inner block to the checkout block, you can change its settings in the `Block Inpector` on the right side of the editor. Within the settings `General` tab you can set its:
- Field type (`Text`, `Select`, `Checkbox`, `Radio` or `Textarea`)
- Label
- Id
- Default value
- Help text

### Input Field **validation** on the frontend

In the `Validation` tab you can toggle the input field as to be required or optional. On the frontend, if a required input field is left empty by the customer, then, upon clicking the **Place Order** button, the `Please fill this field` error message will be shown.

### **Storing the value** of the Input Field

After clicking the **Place Order** button, the value of the `Input Field` will be saved to the database as a custom field associated with the order.

In the `Presentation` tab of the `Input Field` block's settings you can enable:
- the `Show on Order page` option so that the field's value will show up on the `Edit Order` page in the admin
- the `Show on Order Confirmation` option so that the field's value will show up on the customer's `Order Confirmation` page (formally known as `Thank You` page)
- the `Show on Order Email` option so that the field's value will show up in the email the customer receives after placing the order.


== Screenshots ==

1. The places where you can add an inner block

2. Add the `Input Field` block to the checkout

3. Change the field's type, label, id, default value from the settings

4. Input field's validation on the frontend

5. The input field's value on the `Edit Order` page in the admin

6. The input field's value on the customer's `Order Confirmation` page

7. The input field's value in the customer's email

== Changelog ==

= 1.7 =
* 06/04/2025
* Fix: if added directly to the "Checkout Fields" block, then move the input field to its position. With WC9.8+ wasn't working correctly anymore.
* Change: use the default ValidationInputError component from @woocommerce/blocks-checkout

= 1.6 =
* 08/22/2024
* Fix: the validation error for the Select and Textarea input fields was showing up at the bottom of the page

= 1.5 =
* 08/05/2024
* Change: inherit the colors, borders and margins set in the theme's global styles

= 1.4 =
* 05/24/2024
* Fix: Input Field blocks were not shown if the Checkout block was preceded by an HTML block. 

= 1.3 =
* 05/09/2024
* Fix: Validate the Id/Name setting.
* Fix: Sanitize the select and radio options on the frontend. 

= 1.2 =
* 03/07/2024
* Fix: Input Field blocks were not shown if the Checkout block was nested in other blocks.
* Fix: The Additional Information section was showing twice when ordering as a logged in user.

= 1.1 =
* 02/25/2024
* Add description and screenshots
* Declare compatibility WooCommerce 8.6

= 1.0 =
* 02/02/2024
* Initial commit
