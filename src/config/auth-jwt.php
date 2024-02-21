<?php

return [
    "clients" => [
        "auth0" => [
            "client_id" => env("AUTH0_M2M_CLIENT_ID"),
            "client_secret" => env("AUTH0_M2M_CLIENT_SECRET")
        ],
        "cimpress" => [
            "client_id" => env("CIMPRESS_M2M_CLIENT_ID"),
            "client_secret" => env("CIMPRESS_M2M_CLIENT_SECRET")
        ]
    ],
    "allowedAuthIssuers" => [
        "https://cimpress.auth0.com/",
        "https://oauth.cimpress.io/"
    ]
];