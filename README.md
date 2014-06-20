==CheckoutCrypto OpenCart Beta Client==

On CheckoutCrypto.com

* Register at checkoutcrypto.com

* Select Coins in the menu on the left, select "manage" then "add coin" on your preferred cryptocurrency.

* Select Account in the menu on the left, the select generate API key.

==On your site:==

* Install http://www.opencart.com/ and all dependencies

* Extract CheckoutCrypto OpenCart Payment Extension

* Login to the yoursite/admin

* In your admin menu (the top bar), select Extensions->Payment. Select install then edit.  

* The final step is to copy and paste your api key. Go back to checkoutcrypto and copy your API key from the "Account" menu (the same one you generated in step 2).


==CRON for Rates==

* Login to your server's shell, 

* copy ./catalog/controller/payment/cc-rates/* to an appropriate folder for any user account, we'll call this $ABSOLUTEPATH, remember it or write it down.

* edit ratesconfig for your mysql database info, specifically the IP, table, user settings for your CMS

* edit getrate.php for any preferred coins you need to add/remove, if the row doesn't exist in table you'll have to create one in cc_coin.

* run the following, replace user with the preferred cron user.

 sudo su $USER -c "crontab -e"

* add a new line at the bottom of the file:

15,45 * * * * cd $ABSOLUTEPATH && php -f getrate.php 



=== troubleshooting ===

1. Test a product and ensure the coins display, if not repeat step 6, but afterwords select "refresh coins"

Warning: Too many calls to our client without delay or timeout can result in your API key being banned!

2. If in doubt, send us a support ticket from your checkoutcrypto.com account.


more info at www.checkoutcrypto.com/clients

Licensed under the Apache 2.0 license at http://www.apache.org/licenses/LICENSE-2.0.html
