<?php

return [
    'matching_radius_km' => (float) env('RIDE_MATCHING_RADIUS_KM', 5.0),
    'request_expiry_seconds' => (int) env('RIDE_REQUEST_EXPIRY_SECONDS', 30),
];
