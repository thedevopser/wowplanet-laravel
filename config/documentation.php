<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Perimeter
    |--------------------------------------------------------------------------
    |
    | Directories walked for classes, each rooted at the "App\" namespace, and
    | the tree of Markdown pages searched for their names.
    |
    */

    'source_paths' => [
        app_path(),
    ],

    'pages_path' => base_path('documentation'),

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    |
    | Classes that have nothing to describe, each with the reason it is out of
    | the perimeter. An exclusion is a deliberate statement, never a shortcut
    | around writing a page.
    |
    */

    'exclude' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Ceiling
    |--------------------------------------------------------------------------
    |
    | How many classes of the perimeter may still be documented nowhere. It only
    | ever goes down: a rise is a regression, not an adjustment. Zero is the
    | target, reached at the end of the documentation epic.
    |
    */

    'max_undocumented' => 45,

];
