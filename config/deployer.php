<?php

return [
    'allowed_path_prefixes' => array_filter(
        array_map('trim', explode(',', env('DEPLOYER_ALLOWED_PATHS', '/var/www')))
    ),

    'branch_pattern' => '/^[a-zA-Z0-9_\/\.\-]+$/',

    'default_timeout' => (int) env('DEPLOYER_TIMEOUT', 600),

    'branch_cache_ttl' => (int) env('DEPLOYER_BRANCH_CACHE_TTL', 300),

    /*
    | Прятать локальные изменения целевого проекта в stash перед checkout,
    | иначе git роняет шаг. Изменения не теряются — лежат в `git stash list`.
    */
    'auto_stash' => (bool) env('DEPLOYER_AUTO_STASH', true),

    'job_timeout' => (int) env('DEPLOYER_JOB_TIMEOUT', 900),

    /*
    | Git environment for subprocesses (fetch, pull, etc.).
    | On Windows/OpenServer, set DEPLOYER_GIT_USERPROFILE so git can find stored credentials.
    */
    'git_userprofile' => env('DEPLOYER_GIT_USERPROFILE'),

    'git_env' => array_filter([
        'GIT_TERMINAL_PROMPT' => env('DEPLOYER_GIT_TERMINAL_PROMPT', '0'),
        'GCM_INTERACTIVE' => env('DEPLOYER_GCM_INTERACTIVE', 'Never'),
    ]),
];
