<?php declare(strict_types=1);

namespace Tests\Legacy\AlgoWeb\PODataLaravel\Unit\Models;

use AlgoWeb\PODataLaravel\Models\ModelReflectionHelper;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\App;
use Mockery as m;
use POData\Providers\Metadata\SimpleMetadataProvider;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestCastModel;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestExplicitModel;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestGetterModel;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestModel;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMonomorphicManySource;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMonomorphicManyTarget;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMonomorphicOneAndManySource;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMonomorphicOneAndManyTarget;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMonomorphicSource;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMonomorphicTarget;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphManySource;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphManySourceAlternate;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphManySourceWithUnexposedTarget;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphManyToManySource;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphManyToManyTarget;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphOneSource;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphOneSourceAlternate;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphTarget;
use Tests\Legacy\AlgoWeb\PODataLaravel\Facets\Models\TestMorphTargetChild;
use Tests\Legacy\AlgoWeb\PODataLaravel\TestCase as TestCase;

/**
 * Generated Test Class.
 */
class MetadataTraitTest extends TestCase
{
    /**
     * @var \AlgoWeb\PODataLaravel\Models\MetadataTrait
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp() : void
    {
        parent::setUp();
        $this->object = $this->getMockForTrait('\AlgoWeb\PODataLaravel\Models\MetadataTrait');
        $msg          = 'Simple metadata provider cannot be called from metadata trait';
        $meta         = m::mock(SimpleMetadataProvider::class);
        $meta->shouldReceive('resolveResourceProperty')->andThrow(new \Exception($msg))->never();
        $meta->shouldReceive('addEntityType')->andThrow(new \Exception($msg))->never();
        $meta->shouldReceive('addKeyProperty')->andThrow(new \Exception($msg))->never();
        $meta->shouldReceive('addPrimitiveProperty')->andThrow(new \Exception($msg))->never();
        $meta->shouldReceive('addResourceReferenceProperty')->andThrow(new \Exception($msg))->never();
        $meta->shouldReceive('addResourceSetReferenceProperty')->andThrow(new \Exception($msg))->never();
        App::instance('metadata', $meta);
        // Clear any residual metadata bitz from previous runs
        $foo = new TestModel();
        self::resetModel($foo);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    public function tearDown() : void
    {
        parent::tearDown();
    }

    /**
     * @covers \AlgoWeb\PODataLaravel\Models\MetadataTrait::metadata
     */
    public function testMetadataNotAnEloquentModel()
    {
        $class    = get_class($this->object);
        $blewUp   = false;
        $expected = $class;
        $actual   = null;

        try {
            $result = $this->object->metadata();
        } catch (\Exception $e) {
            $actual = $e->getMessage();
        }
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers \AlgoWeb\PODataLaravel\Models\MetadataTrait::metadata
     */
    public function testMetadataTableNotPresent()
    {
        $schemaBuilder = \Mockery::mock(\Illuminate\Database\Schema\Builder::class)->makePartial();
        $schemaBuilder->shouldReceive('hasTable')->andReturn(false);

        $connect = \Mockery::mock(Connection::class)->makePartial();
        $connect->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);

        $foo = \Mockery::mock(TestModel::class)->makePartial();
        $foo->shouldReceive('getConnection')->andReturn($connect);

        $result = $foo->metadata();
        $this->assertTrue(is_array($result));
        $this->assertEquals(0, count($result));
    }

    public function testMetadataGeneration()
    {
        $intType = \Mockery::mock(\Doctrine\DBAL\Types\IntegerType::class);
        $intType->shouldReceive('getName')->andReturn('integer');

        $strType = \Mockery::mock(\Doctrine\DBAL\Types\StringType::class);
        $strType->shouldReceive('getName')->andReturn('string');

        $id = \Mockery::mock(\Doctrine\DBAL\Schema\Column::class)->makePartial();
        $id->shouldReceive('getNotNull')->andReturn(true);
        $id->shouldReceive('getType')->andReturn($intType);

        $name = \Mockery::mock(\Doctrine\DBAL\Schema\Column::class)->makePartial();
        $name->shouldReceive('getNotNull')->andReturn(true);
        $name->shouldReceive('getType')->andReturn($strType);

        $columns = ['id' => $id, 'name' => $name];

        $schemaBuilder = \Mockery::mock(\Illuminate\Database\Schema\Builder::class)->makePartial();
        $schemaBuilder->shouldReceive('hasTable')->andReturn(true);
        $schemaBuilder->shouldReceive('getColumnListing')->andReturn(['id', 'name', 'added_at', 'weight', 'code']);

        $connect = \Mockery::mock(Connection::class)->makePartial();
        $connect->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);
        $connect->shouldReceive('getDoctrineSchemaManager->listTableColumns')->andReturn($columns);

        $foo = \Mockery::mock(TestModel::class)->makePartial();
        $foo->shouldReceive('getConnection')->andReturn($connect);
        $foo->shouldReceive('metadataMask')->andReturn(['id', 'name']);

        $expected         = [];
        $expected['id']   = ['type' => 'integer', 'nullable' => false, 'fillable' => false, 'default' => null];
        $expected['name'] = ['type' => 'string', 'nullable' => false, 'fillable' => true, 'default' => null];

        $result = $foo->metadata();
        $this->assertEquals($expected, $result);
    }

    public function testMetadataGenerationWithGetter()
    {
        $expected               = [];
        $expected['id']         = ['type' => 'integer', 'nullable' => false, 'fillable' => false, 'default' => null];
        $expected['name']       = ['type' => 'string', 'nullable' => false, 'fillable' => true, 'default' => null];
        $expected['added_at']   = ['type' => 'integer', 'nullable' => false, 'fillable' => true, 'default' => null];
        $expected['weight']     = ['type' => 'integer', 'nullable' => false, 'fillable' => true, 'default' => null];
        $expected['code']       = ['type' => 'string', 'nullable' => false, 'fillable' => true, 'default' => null];
        $expected['WeightCode'] = ['type' => 'text', 'nullable' => true, 'fillable' => false, 'default' => ''];

        $intType = \Mockery::mock(\Doctrine\DBAL\Types\IntegerType::class);
        $intType->shouldReceive('getName')->andReturn('integer');

        $strType = \Mockery::mock(\Doctrine\DBAL\Types\StringType::class);
        $strType->shouldReceive('getName')->andReturn('string');

        $id = \Mockery::mock(\Doctrine\DBAL\Schema\Column::class)->makePartial();
        $id->shouldReceive('getNotNull')->andReturn(true);
        $id->shouldReceive('getType')->andReturn($intType);

        $name = \Mockery::mock(\Doctrine\DBAL\Schema\Column::class)->makePartial();
        $name->shouldReceive('getNotNull')->andReturn(true);
        $name->shouldReceive('getType')->andReturn($strType);

        $columns = ['id' => $id, 'name' => $name, 'added_at' => $id, 'weight' => $id, 'code' => $name];

        $schemaBuilder = \Mockery::mock(\Illuminate\Database\Schema\Builder::class)->makePartial();
        $schemaBuilder->shouldReceive('hasTable')->andReturn(true);
        $schemaBuilder->shouldReceive('getColumnListing')->andReturn(['id', 'name', 'added_at', 'weight', 'code']);

        $connect = \Mockery::mock(Connection::class)->makePartial();
        $connect->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);
        $connect->shouldReceive('getDoctrineSchemaManager->listTableColumns')->andReturn($columns);

        $foo = \Mockery::mock(TestGetterModel::class)->makePartial();
        $foo->shouldReceive('getweightAttribute')->andReturn(null);
        $foo->shouldReceive('getConnection')->andReturn($connect);
        self::resetModel($foo);
        $result = $foo->metadata();
        $this->assertEquals(count($expected), count($result));
        foreach ($expected as $key => $val) {
            $this->assertTrue($val === $result[$key]);
        }
    }

    public function testMetadataGenerationFromExplicitModel()
    {
        $expected         = [];
        $expected['id']   = ['type' => 'integer', 'nullable' => false, 'fillable' => false, 'default' => null];
        $expected['name'] = ['type' => 'string', 'nullable' => false, 'fillable' => true, 'default' => null];

        $foo    = new TestExplicitModel();
        $result = $foo->metadata();
        $this->assertEquals(count($expected), count($result));
        foreach ($expected as $key => $val) {
            $this->assertTrue($val === $result[$key]);
        }
    }

    /**
     * @covers \AlgoWeb\PODataLaravel\Models\MetadataTrait::metadataMask
     */
    public function testMetadataMaskNothingHiddenNothingVisible()
    {
        $expected = ['id', 'name', 'added_at', 'weight', 'code'];
        $foo      = \Mockery::mock(TestModel::class)->makePartial();
        $foo->shouldReceive('getHidden')->andReturn([]);
        $foo->shouldReceive('getVisible')->andReturn([]);

        $result     = $foo->metadataMask();
        $expResDiff = array_diff($expected, $result); // values in expected that are not in result, ignoring order
        $resExpDiff = array_diff($result, $expected); // values in result that are not in expected, ignoring order
        $this->assertEquals(0, count($expResDiff) + count($resExpDiff)); // if all keys are common, arrays are equal
    }

    /**
     * @covers \AlgoWeb\PODataLaravel\Models\MetadataTrait::metadataMask
     */
    public function testMetadataMaskNothingHiddenOverlappingVisible()
    {
        $expected = ['name'];
        $foo      = \Mockery::mock(TestModel::class)->makePartial();
        $foo->shouldReceive('getHidden')->andReturn([]);
        $foo->shouldReceive('getVisible')->andReturn(['name', 'height']);

        $result     = $foo->metadataMask();
        $expResDiff = array_diff($expected, $result); // values in expected that are not in result, ignoring order
        $resExpDiff = array_diff($result, $expected); // values in result that are not in expected, ignoring order
        $this->assertEquals(0, count($expResDiff) + count($resExpDiff)); // if all keys are common, arrays are equal
    }

    /**
     * @covers \AlgoWeb\PODataLaravel\Models\MetadataTrait::metadataMask
     */
    public function testMetadataMaskNothingHiddenOverlappingSingleVisible()
    {
        $expected = ['name'];
        $foo      = \Mockery::mock(TestModel::class)->makePartial();
        $foo->shouldReceive('getHidden')->andReturn([]);
        $foo->shouldReceive('getVisible')->andReturn(['name']);

        $result     = $foo->metadataMask();
        $expResDiff = array_diff($expected, $result); // values in expected that are not in result, ignoring order
        $resExpDiff = array_diff($result, $expected); // values in result that are not in expected, ignoring order
        $this->assertEquals(0, count($expResDiff) + count($resExpDiff)); // if all keys are common, arrays are equal
    }

    /**
     * @covers \AlgoWeb\PODataLaravel\Models\MetadataTrait::metadataMask
     */
    public function testMetadataMaskNothingVisibleOverlappingSingleHidden()
    {
        $expected = ['id', 'added_at', 'weight', 'code'];
        $foo      = \Mockery::mock(TestModel::class)->makePartial();
        $foo->shouldReceive('getHidden')->andReturn(['name']);
        $foo->shouldReceive('getVisible')->andReturn([]);

        $result     = $foo->metadataMask();
        $expResDiff = array_diff($expected, $result); // values in expected that are not in result, ignoring order
        $resExpDiff = array_diff($result, $expected); // values in result that are not in expected, ignoring order
        $this->assertEquals(0, count($expResDiff) + count($resExpDiff)); // if all keys are common, arrays are equal
    }

    /**
     * @covers \AlgoWeb\PODataLaravel\Models\MetadataTrait::metadataMask
     */
    public function testMetadataMaskOverlappingHiddenNothingVisible()
    {
        $expected = ['id', 'added_at', 'weight', 'code'];
        $foo      = \Mockery::mock(TestModel::class)->makePartial();
        $foo->shouldReceive('getHidden')->andReturn(['name', 'height']);
        $foo->shouldReceive('getVisible')->andReturn([]);

        $result = $foo->metadataMask();

        $expResDiff = array_diff($expected, $result); // values in expected that are not in result, ignoring order
        $resExpDiff = array_diff($result, $expected); // values in result that are not in expected, ignoring order
        $this->assertEquals(0, count($expResDiff) + count($resExpDiff)); // if all keys are common, arrays are equal
    }

    /**
     * @covers \AlgoWeb\PODataLaravel\Models\MetadataTrait::metadataMask
     */
    public function testMetadataMaskVisibleTakesPrecedenceOverHidden()
    {
        $expected = ['id', 'name'];
        $foo      = \Mockery::mock(TestModel::class)->makePartial();
        $foo->shouldReceive('getHidden')->andReturn(['name', 'id']);
        $foo->shouldReceive('getVisible')->andReturn(['name', 'id']);

        $result = $foo->metadataMask();

        $expResDiff = array_diff($expected, $result); // values in expected that are not in result, ignoring order
        $resExpDiff = array_diff($result, $expected); // values in result that are not in expected, ignoring order
        $this->assertEquals(0, count($expResDiff) + count($resExpDiff)); // if all keys are common, arrays are equal
    }

    /**
     * @dataProvider getRelationshipsFromMethodsProvider
     * @param $model
     * @param $relation
     * @throws \ReflectionException
     */
    public function testGetRelationshipsFromMethods($model, $relation)
    {
        $foo     = new $model();
        $result  = ModelReflectionHelper::getRelationshipsFromMethods($foo);
        $message = sprintf('%s relation not found on %s', $relation, $model);
        $this->assertTrue(in_array($relation, $result), $message);
    }

    public function getRelationshipsFromMethodsProvider()
    {
        return [
            [TestMorphManyToManyTarget::class, 'manyTarget'],
            [TestMorphManyToManySource::class, 'manySource'],
            [TestMorphOneSource::class, 'morphTarget'],
            [TestMorphManySource::class, 'morphTarget'],
            [TestMorphTarget::class, 'morph'],

        ];
    }
    public function testGetDefaultEndpointName()
    {
        $foo = new TestModel();

        $expected = 'TestModel';
        $actual   = $foo->getEndpointName();
        $this->assertEquals($expected, $actual);
    }

    public function testGetEndpointSpecifiedName()
    {
        $foo      = new TestModel(null, 'EndPoint');
        $expected = 'EndPoint';
        $actual   = $foo->getEndpointName();
        $this->assertEquals($expected, $actual);
    }

    public function testGetMetadataMaskWithGetterSet()
    {
        $columns  = ['name', 'added_at', 'weight', 'code'];
        $expected = ['name', 'added_at', 'weight', 'code', 'WeightCode'];

        $mockBuilder = \Mockery::mock(\Illuminate\Database\Schema\MySqlBuilder::class)->makePartial();
        $mockBuilder->shouldReceive('getColumnListing')->andReturn($columns);

        $foo = \Mockery::mock(TestGetterModel::class)->makePartial();
        $foo->shouldReceive('getConnection->getSchemaBuilder')->andReturn($mockBuilder);
        self::resetModel($foo);

        $result = $foo->metadataMask();
        $this->assertEquals(count($expected), count($result));
        for ($i = 0; $i < count($result); $i++) {
            $this->assertEquals($expected[$i], $result[$i]);
        }
    }

    public function testGetSetEagerLoadGoodData()
    {
        $foo       = new TestMonomorphicSource();
        $relations = ['manySource', 'oneSource'];
        $foo->setEagerLoad($relations);
        $result = $foo->getEagerLoad();
        $this->assertEquals($relations, $result);
    }

    public function testSetEagerLoadBadDataIsObject()
    {
        $foo       = new TestMonomorphicSource();
        $relations = [new \DateTime()];

        $expected = 'Object of class DateTime could not be converted to string';
        $actual   = null;

        try {
            $foo->setEagerLoad($relations);
        } catch (\Throwable $e) {
            $actual = $e->getMessage();
        }
        $this->assertEquals($expected, $actual);
    }

    public function testSetEagerLoadBadDataIsArray()
    {
        $foo       = new TestMonomorphicSource();
        $relations = [[]];

        $expected = 'Array to string conversion';
        $actual   = null;

        try {
            $foo->setEagerLoad($relations);
        } catch (\Exception $e) {
            $actual = $e->getMessage();
        }
        $this->assertEquals($expected, $actual);
    }
    

    public function testSetEagerLoadMalformedPayloadObject()
    {
        $foo = new TestMonomorphicSource();

        $expected = 'Object of class stdClass could not be converted to string';
        $actual   = null;

        try {
            $foo->setEagerLoad(['foobar', new \stdClass()]);
        } catch (\Throwable $e) {
            $actual = $e->getMessage();
        }
        $this->assertEquals($expected, $actual);
    }
}
