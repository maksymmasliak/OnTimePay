<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (App::runningInConsole() && !App::runningUnitTests()) {
            return;
        }

        if (!Auth::check() || !Auth::user()->company_id) {
            throw new \RuntimeException(
                'CompanyScope: attempted to query ' . $model::class . ' without an authenticated company context.'
            );
        }

        $builder->where($model->getTable() . '.company_id', Auth::user()->company_id);
    }
}
