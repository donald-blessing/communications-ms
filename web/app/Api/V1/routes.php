<?php

/**
 * @var Laravel\Lumen\Routing\Router $router
 */
$router->group([
    'prefix' => env('APP_API_VERSION', ''),
    'namespace' => '\App\Api\V1\Controllers',
], function ($router) {
    /**
     * Internal access
     */
    $router->group([
        'middleware' => 'checkUser',
    ], function ($router) {
        /**
         * Channels Auth
         */
        $router->group([
            'prefix' => 'channels',
        ], function ($router) {
            $router->get('/auth/{platform}', 'ChannelController');
        });

        $router->get('/bot/{messengerInstance}/send-message', 'MessagesController@sendMessage');
        $router->get('/bot/{messengerInstance}/webhook', 'MessagesController@handleWebhook');
    });

    /**
     * Messenger instance
     */
    $router->group([
        'prefix' => 'messages',
    ], function ($router) {
        $router->get('/{messengerInstance}/send-message', 'MessagesController@sendMessage');
        $router->get('/{messengerInstance}/webhook', 'MessagesController@handleWebhook');
    });


    /**
     * ADMIN PANEL
     */
    $router->group([
        'prefix' => 'admin',
        'namespace' => 'Admin',
        'middleware' => [
            'checkUser',
            'checkAdmin',
        ],
    ], function ($router) {
        /**
         * Channels (Bots)
         */
        $router->group([
            'prefix' => 'bots',
        ], function ($router) {
            $router->get('/', 'BotController@index');
            $router->post('/', 'BotController@store');
            $router->get('/{id:[a-fA-F0-9\-]{36}}', 'BotController@show');
            $router->put('/{id:[a-fA-F0-9\-]{36}}', 'BotController@update');
            $router->delete('/{id:[a-fA-F0-9\-]{36}}', 'BotController@destroy');
            $router->post('/{id:[a-fA-F0-9\-]{36}}/update-status', 'BotController@updateStatus');
        });
    });
});
