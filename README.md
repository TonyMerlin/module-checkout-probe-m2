## Install / Enable

cd /<magentoroot>/


bin/magento module:enable Merlin_CheckoutProbe
bin/magento setup:upgrade
bin/magento cache:flush


# enable probe

bin/magento merlin:checkoutprobe:enable


##How to run your Klarna tests and catch the failure


#Tail the probe log

tail -f var/log/merlin_checkout_probe.log


#Query the DB table around a failure window

SELECT entity_id, created_at, full_action_name, event_type, quote_id, order_increment_id, redirect_to
FROM merlin_checkoutprobe_event
ORDER BY entity_id DESC
LIMIT 50;


#Find “back to cart” redirects with stack traces

SELECT created_at, full_action_name, quote_id, redirect_to, context_json
FROM merlin_checkoutprobe_event
WHERE event_type='redirect'
  AND redirect_to LIKE '%/checkout/cart%'
ORDER BY entity_id DESC
LIMIT 20;


#Find exceptions for the same quote

SELECT created_at, full_action_name, event_type, quote_id, context_json
FROM merlin_checkoutprobe_event
WHERE quote_id = 279966
ORDER BY entity_id ASC;
