<?php

return [
    'name' => env('CAMPUS_NAME', 'Telkom University Surabaya'),
    'email_domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => mb_strtolower(trim($domain)),
        explode(',', (string) env('CAMPUS_EMAIL_DOMAINS', 'kampus.ac.id'))
    ))),
];
