<?php

namespace App\Nova;

use App\Http\Controllers\Admin\ChromaController;
use http\Env\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Embedding extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Files>
     */
    public static $model = \App\Models\Files::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Embedding ID')
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            Text::make('Name')
                ->onlyOnIndex(),

            File::make('File', 'path')
                ->acceptedTypes('.pdf,.txt')
                ->rules('mimes:pdf,txt', function ($attribute, $value, $fail) {
                    if (str_contains($value->getClientOriginalName(), '/') || str_contains($value->getClientOriginalName(), '\\')) {
                        $fail('The filename cannot contain the "/" character.');
                    }
                })
                ->storeOriginalName('name')
                ->storeSize('size')
                ->path('/uploads')
                ->readonly(function() {
                    return (bool)$this->resource->id;
                })
                ->disk('local'),

            Number::make('User ID', 'user_id')
                ->default(Auth::id())
                ->withMeta(['extraAttributes' => [
                    'readonly' => true
                ]]),

            BelongsTo::make('Collection')
                ->readonly(function() {
                    return (bool)$this->resource->id;
                }),
        ];
    }

    public static function afterCreate(NovaRequest $request, Model $model)
    {
        if (!ChromaController::createEmbedding($model)) {
            abort(500, 'Error creating embedding');
        }
    }

    public static function afterDelete(NovaRequest $request, Model $model)
    {
        if (!ChromaController::deleteEmbedding($model)) {
            abort(500, 'Error deleting embedding');
        }

        $model->forceDelete();
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
