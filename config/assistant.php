<?php

return [
    /*
    | Asisten ketersediaan barang.
    | Tanpa API key, asisten memakai mode aturan (jawaban disusun oleh sistem).
    | Bila ANTHROPIC_API_KEY diisi di file .env, jawaban ketersediaan dirapikan
    | oleh model Claude. Datanya tetap diambil dari database, bukan karangan model.
    | Jangan pernah menulis API key di file yang di-commit.
    */
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ASSISTANT_MODEL', 'claude-haiku-4-5-20251001'),

    // Jumlah barang maksimal yang ditampilkan dalam satu jawaban
    'max_items' => 15,
];
