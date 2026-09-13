<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base Eloquent model for the Priyasa commerce database.
 *
 * Keeping the connection here prevents a model from silently falling back to
 * Laravel's default application database when PriyasaCore is installed with
 * a dedicated commerce database.
 */
abstract class PriyasaModel extends Model
{
    protected $connection = 'priyasa';
}
