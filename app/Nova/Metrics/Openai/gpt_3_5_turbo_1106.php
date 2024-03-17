<?php

namespace App\Nova\Metrics\Openai;

use App\Models\Messages;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class gpt_3_5_turbo_1106 extends Model
{
    // Price in dollar per million tokens
    public float $input = 1.00;
    public float $output = 2.00;

    public $name = 'gpt-3.5-turbo-1106';

    public $width = '1/2';
}
