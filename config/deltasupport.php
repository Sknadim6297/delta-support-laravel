<?php

return [
    /*
    | Inherited investment, wallet transfer, and commission edits stay in the
    | codebase. They are closed in the running application until a later phase
    | explicitly enables them. Tests turn this on so existing coverage still runs.
    */
    'legacy_financial_actions' => (bool) env('LEGACY_FINANCIAL_ACTIONS', false),
];
