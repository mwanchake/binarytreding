<?php

return [
    'minimum_amount' => (float) env('WITHDRAWAL_MINIMUM_AMOUNT', 10),
    'maximum_amount' => (float) env('WITHDRAWAL_MAXIMUM_AMOUNT', 100000),
    'methods' => [
        'mpesa' => [
            'label' => 'M-Pesa',
            'fee_percent' => (float) env('WITHDRAWAL_MPESA_FEE_PERCENT', 0),
            'fee_fixed' => (float) env('WITHDRAWAL_MPESA_FEE_FIXED', 0),
        ],
        'binance_usdt_bep20' => [
            'label' => 'Binance USDT (BEP20)',
            'fee_percent' => (float) env('WITHDRAWAL_BEP20_FEE_PERCENT', 0),
            'fee_fixed' => (float) env('WITHDRAWAL_BEP20_FEE_FIXED', 0),
        ],
    ],
];
