<?php

/*
|--------------------------------------------------------------------------
| Datensicherungen: Wiederherstellung aus Server-Snapshots (docs/betrieb.md)
|--------------------------------------------------------------------------
|
| Die Seite Admin > Datensicherungen (nur Admin) listet die Snapshots des Server-Skripts /usr/local/bin/app-restore
| (per sudo, ohne Passwort) und stoesst eine Wiederherstellung an. Ohne BACKUP_APP ist die Seite aus. Der App-Name
| kommt nur aus dieser Config, nie aus Benutzereingaben. Gleicher Baustein wie in Tourtool.
|
*/

return [
    'app' => env('BACKUP_APP'),

    'command' => '/usr/local/bin/app-restore',

    'list_cache_seconds' => 60,
];
