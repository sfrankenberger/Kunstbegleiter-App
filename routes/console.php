<?php

use Illuminate\Support\Facades\Schedule;

// Aenderungsprotokoll aufraeumen, Aufbewahrung 2 Jahre (config/activitylog.php)
Schedule::command('activitylog:clean')->dailyAt('04:20');
