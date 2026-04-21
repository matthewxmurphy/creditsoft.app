<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CreditSoft | API Explorer Frame</title>
        @vite(['resources/js/swagger.js'])
        <style>
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
                background: #ffffff;
                color: #1c1917;
                font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
            }

            #swagger-ui {
                min-height: 100%;
            }

            .swagger-ui .topbar {
                display: none;
            }

            .swagger-ui .scheme-container {
                box-shadow: none;
                background: rgba(250, 250, 249, 0.88);
            }

            .swagger-ui .opblock-tag {
                border-bottom-color: rgba(168, 162, 158, 0.45);
            }

            .swagger-ui .btn.authorize {
                border-color: #1c1917;
                color: #1c1917;
            }
        </style>
    </head>
    <body>
        <div id="swagger-ui" data-spec-url="{{ $specUrl }}"></div>
    </body>
</html>
