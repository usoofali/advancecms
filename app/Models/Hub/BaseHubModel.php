<?php

namespace App\Models\Hub;

use Illuminate\Database\Eloquent\Model;

abstract class BaseHubModel extends Model
{
    /**
     * The database connection name for Hub models.
     *
     * @var string|null
     */
    protected $connection = 'hub_mysql';
}
