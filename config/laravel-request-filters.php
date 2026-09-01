<?php

return [

    'models_folder' => app_path('Models'),

    /*
     * Extra middleware applied to the /filters/metadata routes, on top of the
     * built-in authorization check (allowed only in local/testing by default -
     * see RequestFiltersServiceProvider::auth() to change who is authorized).
     */
    'metadata_middleware' => [],

];
