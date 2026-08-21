<?php

return [
    'allowed_path_prefixes' => array_filter(
        array_map('trim', explode(',', env('DEPLOYER_ALLOWED_PATHS', '/var/www')))
    ),

    'branch_pattern' => '/^[a-zA-Z0-9_\/\.\-]+$/',

    /*
    | Что не копировать при дубле файлов инстанса (rsync --exclude, через запятую).
    | По умолчанию не тащим то, что ставится заново composer/frontend-шагами того же деплоя,
    | и мусор рантайма. `.env` исключён всегда: он копируется отдельно и с обнулёнными
    | DB_DATABASE/APP_URL. `.git` копируется — без него в копию нельзя деплоить.
    |
    | bootstrap/cache/ исключён по той же причине, что обнуляется DB_DATABASE: там лежит
    | закэшированный конфиг оригинала, и копия с ним ходила бы в базу оригинала мимо
    | собственного .env. packages.php/services.php оттуда не жалко — их пересоберёт
    | composer-шаг того же деплоя, а без него Laravel допишет их сам при первой загрузке.
    */
    'copy_excludes' => array_values(array_filter(
        array_map('trim', explode(',', env(
            'DEPLOYER_COPY_EXCLUDES',
            'node_modules/,vendor/,bootstrap/cache/,storage/logs/,storage/framework/cache/,storage/framework/sessions/,storage/framework/views/'
        )))
    )),

    'default_timeout' => (int) env('DEPLOYER_TIMEOUT', 600),

    'branch_cache_ttl' => (int) env('DEPLOYER_BRANCH_CACHE_TTL', 300),

    /*
    | Прятать локальные изменения целевого проекта в stash перед checkout,
    | иначе git роняет шаг. Изменения не теряются — лежат в `git stash list`.
    */
    'auto_stash' => (bool) env('DEPLOYER_AUTO_STASH', true),

    'job_timeout' => (int) env('DEPLOYER_JOB_TIMEOUT', 900),

    /*
    | Куда `scripts/backup-db.sh` складывает дампы и сколько последних держать на инстанс.
    | Значения подставляются в команду шага backup, которую панель предлагает новому инстансу;
    | уже созданным они ничего не меняют — там команда записана в поле инстанса.
    |
    | На Windows подсказки по умолчанию нет: скрипт bash-овый, и /var/backups там ни при чём.
    | Нужна — пропишите свой путь в DEPLOYER_BACKUP_ROOT.
    */
    'backup_root' => env('DEPLOYER_BACKUP_ROOT', PHP_OS_FAMILY === 'Windows' ? '' : '/var/backups/deployer'),

    'backup_keep' => (int) env('DEPLOYER_BACKUP_KEEP', 10),

    /*
    | Слать инициатору письмо, когда деплой завершился (успех или падение). Требует
    | настроенной почты (MAIL_*); при выключенном — письма не отправляются.
    */
    'notify_on_finish' => (bool) env('DEPLOYER_NOTIFY_ON_FINISH', false),

    /*
    | Уведомление о завершении деплоя в самой панели (колокольчик в шапке). В отличие
    | от письма ничего не требует настраивать, поэтому включено по умолчанию.
    */
    'notify_in_panel' => (bool) env('DEPLOYER_NOTIFY_IN_PANEL', true),

    /*
    | Через сколько секунд без единой записи в БД считать деплой брошенным.
    | Убитый воркер оставляет строку в running навсегда, а она блокирует запуск
    | новых деплоев по инстансу. Живой деплой пишет лог и трогает updated_at,
    | а на job_timeout его в любом случае снимает очередь — поэтому берём с запасом.
    */
    'stale_after' => (int) env('DEPLOYER_STALE_AFTER', 960),

    /*
    | Сколько секунд деплой может простоять в pending, прежде чем страница
    | предположит, что queue:work не запущен.
    */
    'queue_warn_after' => (int) env('DEPLOYER_QUEUE_WARN_AFTER', 15),

    /*
    | Git environment for subprocesses (fetch, pull, etc.).
    | On Windows/OpenServer, set DEPLOYER_GIT_USERPROFILE so git can find stored credentials.
    */
    'git_userprofile' => env('DEPLOYER_GIT_USERPROFILE'),

    'git_env' => array_filter([
        'GIT_TERMINAL_PROMPT' => env('DEPLOYER_GIT_TERMINAL_PROMPT', '0'),
        'GCM_INTERACTIVE' => env('DEPLOYER_GCM_INTERACTIVE', 'Never'),
    ]),

    /*
    | Просмотр и правка .env целевого проекта в админке. Наружу отдаются только ключи из
    | env_visible_keys — остальные попадают в ответ лишь счётчиком. Значения ключей,
    | подходящих под env_masked_patterns, маскируются до отправки в браузер.
    |
    | Этот же список ограничивает запись: менять можно только перечисленные ключи.
    | Пустое значение замаскированного ключа означает «не трогать» — очистить секрет
    | из панели нельзя, только заменить.
    */
    'env_visible_keys' => [
        'APP_NAME',
        'APP_ENV',
        'APP_KEY',
        'APP_DEBUG',
        'APP_URL',
        'LOG_CHANNEL',
        'LOG_LEVEL',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'CACHE_STORE',
        'QUEUE_CONNECTION',
        'SESSION_DRIVER',
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_FROM_ADDRESS',
    ],

    'env_masked_patterns' => [
        '*KEY*',
        '*SECRET*',
        '*PASSWORD*',
        '*TOKEN*',
        '*CREDENTIALS*',
        '*_DSN',
    ],

    'env_max_size' => (int) env('DEPLOYER_ENV_MAX_SIZE', 262144),

    /*
    | Таймаут health-пинга URL инстанса. Держим коротким: запрос синхронный,
    | его ждёт открытая страница инстанса.
    */
    'health_timeout' => (int) env('DEPLOYER_HEALTH_TIMEOUT', 5),

    /*
    | Чьи screen-сессии не показывать на Admin → Screens без явного запроса. По умолчанию
    | root: его сессии — это системные демоны, а не стенды, и в списке они только мешают.
    | Скрытые пользователи остаются в фильтре, выбор в нём показывает их сессии.
    */
    'screen_hidden_users' => array_values(array_filter(
        array_map('trim', explode(',', env('DEPLOYER_SCREEN_HIDDEN_USERS', 'root')))
    )),

    /*
    | Чем поднимать стенд кнопкой Start на Admin → Screens. Команда выполняется внутри
    | screen-сессии, в каталоге инстанса; `{port}` подставляется из настройки инстанса.
    | Хост 0.0.0.0 — иначе стенд виден только с самой машины.
    */
    'serve_command' => env('DEPLOYER_SERVE_COMMAND', 'php artisan serve --host=0.0.0.0 --port={port}'),

    /*
    | Сколько ждать после `screen -dmS`, прежде чем проверить, что сессия жива. Сама screen
    | всегда выходит с нулём — упавшую внутри команду видно только по отсутствию сессии.
    */
    'screen_start_check_delay' => (int) env('DEPLOYER_SCREEN_START_CHECK_DELAY', 700),

    /*
    | Вторая ссылка на стенд — для тех, кто ходит к изолированному серверу через ssh-туннель:
    | оригинальный адрес у них не открывается, а проброшенный порт — да. Адрес строится из
    | `serve_port` инстанса, `{port}` — единственный плейсхолдер. Пустое значение убирает
    | ссылку из панели. Health-пинг эту ссылку не трогает: он ходит с хоста панели, где
    | localhost — сама панель.
    */
    'tunnel_url_template' => env('DEPLOYER_TUNNEL_URL_TEMPLATE', 'http://localhost:{port}'),
];
