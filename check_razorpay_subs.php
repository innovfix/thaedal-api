<?php
require_once __DIR__ . /vendor/autoload.php;

 = getenv(RAZORPAY_KEY_ID) ?: trim(shell_exec(grep RAZORPAY_KEY_ID .env | cut -d= -f2));
 = getenv(RAZORPAY_KEY_SECRET) ?: trim(shell_exec(grep RAZORPAY_KEY_SECRET .env | cut -d= -f2));

 = new Razorpay\Api\Api(, );

// Check a few subscriptions
 = [sub_S3NC3jZSNwPHV6, sub_S7fGVKVSgPR8p1, sub_S8BW2f7ak8X0mo];

foreach ( as ) {
    try {
         = ->subscription->fetch();
        echo \n===  ===\n;
        echo Status:  . ->status . \n;
        echo Paid Count:  . (->paid_count ?? 0) . \n;
        echo Auth Attempts:  . (->auth_attempts ?? 0) . \n;
        echo Charge At:  . (->charge_at ? date(Y-m-d H:i:s, ->charge_at) : NULL) . \n;
        echo Current End:  . (->current_end ? date(Y-m-d H:i:s, ->current_end) : NULL) . \n;
    } catch (Exception ) {
        echo : ERROR -  . ->getMessage() . \n;
    }
}
