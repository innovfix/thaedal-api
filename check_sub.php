<?php
require " vendor/autoload.php\;
\ = require_once \bootstrap/app.php\;
\->make(\Illuminate\\\\Contracts\\\\Console\\\\Kernel\)->bootstrap();
\ = new Razorpay\\\\Api\\\\Api(env(\RAZORPAY_KEY_ID\), env(\RAZORPAY_KEY_SECRET\));
\ = \->subscription->fetch(\sub_S6WKnRVAIvESYg\);
echo \Status: \ . \->status . \\\n\;
echo \Paid Count: \ . \->paid_count . \\\n\;
echo \Total Count: \ . \->total_count . \\\n\;
print_r(\->toArray());

