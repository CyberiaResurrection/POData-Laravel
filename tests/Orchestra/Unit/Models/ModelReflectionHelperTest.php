<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 1/03/20
 * Time: 1:53 PM
 */

namespace AlgoWeb\PODataLaravel\Orchestra\Tests\Unit\Models;

use AlgoWeb\PODataLaravel\Models\ModelReflectionHelper;
use AlgoWeb\PODataLaravel\Orchestra\Tests\Models\OrchestraPolymorphToManySourceMalformedModel;
use AlgoWeb\PODataLaravel\Orchestra\Tests\TestCase;

class ModelReflectionHelperTest extends TestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testGetCodeForSingleLineMethod()
    {
        $foo = new OrchestraPolymorphToManySourceMalformedModel();
        $reflec = new \ReflectionClass($foo);

        $method = $reflec->getMethod('sourceChildren');

        $expected = 'public function sourceChildren() { return $this->morphToMany'
                    .'(OrchestraPolymorphToManyTestModel::class, \'manyable\', \'test_manyables\', \'manyable_id\','
                    .' \'many_id\'); }';
        $actual = ModelReflectionHelper::getCodeForMethod($method);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetCodeForSingleLineMethodJustAfterAnotherMethod()
    {
        $foo = new OrchestraPolymorphToManySourceMalformedModel();
        $reflec = new \ReflectionClass($foo);

        $method = $reflec->getMethod('child');

        $expected = 'public function child() { return $this->morphMany(OrchestraMorphToTestModel::class, \'morph\');}';
        $actual = ModelReflectionHelper::getCodeForMethod($method);
        $this->assertEquals($expected, $actual);
    }
}
