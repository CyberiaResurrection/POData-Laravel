<?php declare(strict_types=1);

namespace Tests\Northwind\AlgoWeb\PODataLaravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StringModel.
 */
class StringModel extends Model
{
    use \AlgoWeb\PODataLaravel\Models\MetadataTrait;
    protected $table = 'strings';

    protected $primaryKey = 'string_id';

    public $timestamps = false;

    protected $fillable = [
        'string_data'
    ];

    protected $guarded = [];
}
