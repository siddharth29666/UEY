<?php

return [
    'ride' => [
        'requested' => 'A new ride has been requested.',
        'accepted' => 'Your ride request has been accepted by :driver.',
        'arriving' => 'Your driver is arriving.',
        'arrived' => 'Your driver has arrived at the pickup location.',
        'started' => 'Your ride has started. Have a safe trip!',
        'completed' => 'Your ride has completed. Thank you for riding with us!',
        'cancelled' => 'The ride has been cancelled.',
    ],
    'wallet' => [
        'topup' => 'Your wallet top-up of :amount was successful.',
        'credit' => 'Your wallet has been credited with :amount.',
        'debit' => 'Your wallet has been debited by :amount.',
        'withdraw_requested' => 'Your withdrawal request of :amount has been submitted.',
        'withdraw_approved' => 'Your withdrawal request of :amount has been approved.',
        'withdraw_rejected' => 'Your withdrawal request of :amount was rejected.',
        'withdraw_completed' => 'Your withdrawal request of :amount is completed.',
    ],
    'payment' => [
        'success' => 'Payment of :amount was processed successfully.',
        'failed' => 'Payment of :amount failed.',
    ],
    'review' => [
        'received' => 'You have received a new review rating: :rating stars.',
    ],
    'driver' => [
        'document_approved' => 'Your document has been approved.',
        'document_rejected' => 'Your document was rejected.',
    ],
    'auth' => [
        'login_new_device' => 'A login from a new device was detected.',
    ],
    'admin' => [
        'broadcast' => ':message',
    ],
    'referral_bonus' => 'Referral bonus of :amount credited to your wallet.',
    'referral' => [
        'applied' => 'Referral code has been successfully applied to your account.',
        'completed' => 'Referral completed successfully for your friend :friend.',
        'first_ride_completed' => 'You have completed your first ride! Referral rewards are being processed.',
        'bonus_received' => 'You received a referral bonus of :amount!',
    ],
    'emergency' => [
        'triggered' => 'Emergency SOS triggered on Ride #:ride_id.',
        'acknowledged' => 'SOS Alert has been acknowledged by the driver.',
        'assigned' => 'SOS Alert has been assigned to an administrator.',
        'resolved' => 'SOS Alert has been resolved.',
        'closed' => 'SOS Alert has been closed.',
    ],
];
