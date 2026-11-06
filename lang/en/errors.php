<?php

return [
    'back_to_store' => 'Return to HS Shop',
    'status' => [
        403 => ['title' => 'Access denied', 'description' => 'Your account is not allowed to open this page.'],
        404 => ['title' => 'Page not found', 'description' => 'The page may have moved or the address may be incorrect.'],
        419 => ['title' => 'Session expired', 'description' => 'Refresh the page and try your action again.'],
        429 => ['title' => 'Too many requests', 'description' => 'Please wait a moment before trying again.'],
        500 => ['title' => 'Something went wrong', 'description' => 'We could not complete the request. Please try again later.'],
    ],
];
