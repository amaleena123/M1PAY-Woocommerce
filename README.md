# M1Pay WooCommerce Payment Gateway

WooCommerce payment method for M1Pay (Hosted Checkout) with **Blocks** support.

## Features
- Classic & Block-based Checkout compatibility  
- Sandbox/Production switch  
- Stores transaction ID on order and order notes

## Requirements
- WordPress 6.0+  
- WooCommerce 7.0+  
- PHP 7.4+

## Quick Start
1. Download the zip file.
2. Upload the zip file in Wordpress Plugin page or clone to `wp-content/plugins`. Make sure the destination path is like this `wp-content/plugins/m1pay-block-gateway`
3. Activate in **Plugins**
4. Configure in **WooCommerce → Settings → Payments → M1Pay**
5. Open M1Pay Merchant Portal and set as the following:
   1. The **Redirect URL** in setting page:  
      *https://{merchant-domain}/wc-api/m1pay_response/*    
   2. For **Host-to-Host callback URL** in setting page  
      *https://{merchant-domain}/wc-api/m1pay_callback/*    
6. Make sure your permalink must set like this in Wordpress setting:
   <img width="935" height="530" alt="image" src="https://github.com/user-attachments/assets/5a3aefda-dec9-405b-82bf-cbcc901a5c9c" />

   Must set the permalink like this : **https://{your_domain}/%postname%/**  

# MUST Requirements
Register to M1Pay first  

# Process to Setup M1Pay Payment Integration
1- Initial setup usualy on M1Pay UAT Environment before live in production server.
2- After registration and M1 approve, merchant may obtain the credential such as  
   - Client ID  
   - Client Secret  
   - File of private key  
   - File of public key  
   These information can be found in M1Pay Merchant Portal.

# Contact M1Pay for
Registration : bd@mobilityone.com.my  
Transaction Inquiries : ccc@mobilityone.com.my  
Technical Issues Related to M1Pay-WooCommerce : amalina.works@gmail.com

## License
M1Pay WooCommerce Payment Gateway
Copyright (C) 2025 Amalina Nusyirwan

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see https://www.gnu.org/licenses/gpl-2.0.html


## Credits
© 2025 Amalina Nusyirwan
