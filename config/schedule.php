<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frequentie van geplande commando's
    |--------------------------------------------------------------------------
    |
    | Cron-expressies voor commando's die per omgeving anders moeten draaien.
    | De defaults hieronder zijn wat acceptatie en productie draaien; zet een
    | afwijkende waarde in .env om een omgeving rustiger (of drukker) te maken.
    |
    | Een waarde is een volledige cron-expressie, bijvoorbeeld "* * * * *" voor
    | elke minuut, "0 * * * *" voor elk heel uur of "0 7 * * *" voor 07:00.
    |
    */

    'emails_sync_graph_cron' => env('SCHEDULE_EMAILS_SYNC_GRAPH_CRON', '* * * * *'),

];
