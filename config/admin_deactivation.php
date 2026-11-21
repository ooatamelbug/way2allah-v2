<?php

return [

    /*
    |--------------------------------------------------------------------------
    | F02 — administrator deactivation (Laravel side)
    |--------------------------------------------------------------------------
    |
    | Default OFF. While off, nothing reads or writes `admin_account_states`:
    | no state query on any request, no deactivate / reactivate routes, no UI.
    |
    | Enabling requires, first: the `create_admin_account_states_table`
    | migration applied on the `main` connection (= the legacy schema), and the
    | owner's approval. See docs/reports/admincp-f01-f10-closure-results.md.
    |
    | Do not switch it off again while deactivated admins exist: their
    | credentials stay neutralised (no login), but the explicit state check
    | stops and reactivation becomes unavailable until it is back on.
    |
    */

    'enabled' => (bool) env('ADMIN_DEACTIVATION_ENABLED', false),

];
