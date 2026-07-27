<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model for all data physically stored in an examination database.
 */
abstract class ExaminationModel extends Model
{
    /**
     * Every examination-domain model uses the runtime connection.
     *
     * @var string|null
     */
    protected $connection = 'exam';
}
