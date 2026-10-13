<?php

use Illuminate\Support\Facades\Schedule;

// Aenderungsprotokoll aufraeumen, Aufbewahrung 2 Jahre (config/activitylog.php)
// --force: in Produktion fragt der Befehl sonst nach und bricht im Scheduler mit Exit-Code 1 ab (seit 08.10.2026)
Schedule::command('activitylog:clean --force')->dailyAt('04:20');
