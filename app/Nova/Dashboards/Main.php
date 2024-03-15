<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\Collections;
use App\Nova\Metrics\ConversationsPerDay;
use App\Nova\Metrics\Embeddings;
use App\Nova\Metrics\MessagesPerDay;
use App\Nova\Metrics\Tokens;
use App\Nova\Metrics\TotalCosts;
use App\Nova\Metrics\TotalTokens;
use App\Nova\Metrics\Users;
use Laravel\Nova\Cards\Help;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return [
            new TotalCosts(),
            new Tokens(),
            new Users(),
            new ConversationsPerDay,
            new MessagesPerDay(),
            new Collections(),
            new Embeddings(),
        ];
    }

    public $showRefreshButton = true;
}
