<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class HotelScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // Only apply scope if user is authenticated and has a current hotel
        if (auth()->check() && auth()->user()->current_hotel_id) {
            // Don't apply scope to super admins
            if (auth()->user()->is_super_admin ?? false) {
                return;
            }

            $builder->where($model->getTable() . '.hotel_id', auth()->user()->current_hotel_id);
        }
    }
}