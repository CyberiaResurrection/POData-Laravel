<?php

declare(strict_types=1);

namespace AlgoWeb\PODataLaravel\Auth;

use AlgoWeb\PODataLaravel\Enums\ActionVerb;
use AlgoWeb\PODataLaravel\Interfaces\AuthInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Class NullAuthProvider
 * @package AlgoWeb\PODataLaravel\Auth
 */
class NullAuthProvider implements AuthInterface
{
    /**
     * Is the requester permitted to perform the requested action on the model class (and instance, if supplied)?
     *
     * @param ActionVerb          $verb
     * @param class-string|null   $modelname Model class to access
     * @param Model|Relation|null $model     Specific model or relation to access
     *
     * @return bool
     */
    public function canAuth(ActionVerb $verb, $modelname, $model = null)
    {
        return true;
    }
}
