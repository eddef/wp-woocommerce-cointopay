Provided by Cointopay.com

This script is free of charge and as you can see open source.
Feel free to comment support@cointopay.com

About
- Crypto payments via Cointopay for WooCommerce.
- Version 0.1

System Requirements
- -
- Cointopay SecurityCode
- Cointopay MerchantID
- Cointopay AltCoinID (for prefered checkout option)
+ Curl PHP Extension
+ JSON Encode

Configuration Instructions
- 
    1. Install zip file using WordPress built-in Add New Plugin installer;

    2. Go to your WooCommerce Settings, and click the Checkout tab, find C2P/Cointopay.
  
    3. In settings "MerchantID" <- set your Cointopay ID.
    4. In settings "Security Code" <- set your Cointopay Security code (no API key required)
    5. Set the AltCoinID, this can also be found in the Account section of Cointopay. Default is 1 for bitcoin, 2 litecoin, 8 darkcoin etc etc.
    6. Make sure to set the Confirm URL to: your url with the following appended: /?wc-api=WC_C2P

Tested on:
- 
- WordPress 3.8.1
- WooCommerce 2.1.9
