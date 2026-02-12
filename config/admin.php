<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Home Route & Permission
    |--------------------------------------------------------------------------
    |
    | The named route users are redirected to after login or when they visit
    | the admin panel root. Change these values to alter the start screen
    | for every user.
    |
    | home_route      – Laravel named route (e.g. 'admin.leads.index')
    | home_permission – ACL key the user must have to access the home route
    |
    */

    'home_route'      => 'admin.leads.index',
    'home_permission' => 'leads',

];
