<?php

return [
    /*
    |--------------------------------------------------------------------------
    | New JPBA achievement master cutover date
    |--------------------------------------------------------------------------
    |
    | Before this date, automatically detected score records are treated as
    | historical detail backfill and do not increase the migrated aggregate.
    | On or after this date, confirmed records increase the new JPBA master
    | aggregate. Keep this null until the public-site cutover date is fixed.
    |
    */
    'cutover_date' => env('JPBA_ACHIEVEMENT_CUTOVER_DATE'),
];
