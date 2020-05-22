<?php

declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 16/02/20
 * Time: 12:12 AM.
 */
namespace AlgoWeb\PODataLaravel\Serialisers;

use Illuminate\Database\Eloquent\Model;
use POData\Common\InvalidOperationException;
use POData\Common\Messages;
use POData\ObjectModel\ODataBagContent;
use POData\ObjectModel\ODataProperty;
use POData\ObjectModel\ODataPropertyContent;
use POData\Providers\Metadata\ResourceProperty;
use POData\Providers\Metadata\ResourcePropertyKind;
use POData\Providers\Metadata\ResourceType;
use POData\Providers\Metadata\ResourceTypeKind;
use POData\Providers\Metadata\Type\Binary;
use POData\Providers\Metadata\Type\Boolean;
use POData\Providers\Metadata\Type\DateTime;
use POData\Providers\Metadata\Type\IType;
use POData\Providers\Metadata\Type\StringType;

abstract class SerialiserLowLevelWriters
{
    /**
     * @param Model           $entryObject
     * @param ModelSerialiser $modelSerialiser
     * @param array[]         $nonRelProp
     * @throws InvalidOperationException
     * @return ODataPropertyContent
     */
    public static function writePrimitiveProperties(
        Model $entryObject,
        ModelSerialiser $modelSerialiser,
        array $nonRelProp
    ): ODataPropertyContent {
        $propertyContent = new ODataPropertyContent([]);
        $cereal          = $modelSerialiser->bulkSerialise($entryObject);
        $cereal          = array_intersect_key($cereal, $nonRelProp);

        foreach ($cereal as $corn => $flake) {
            $corn  = strval($corn);
            $rType = $nonRelProp[$corn]['type'];
            $value = isset($flake) ? SerialiserLowLevelWriters::primitiveToString($rType, $flake) : null;

            /** @var ResourceProperty $nrp */
            $nrp                                = $nonRelProp[$corn]['prop'];
            $subProp                            = new ODataProperty($corn, $nrp->getResourceType()->getFullName(), $value);
            $propertyContent[$corn]             = $subProp;
        }
        return $propertyContent;
    }

    /**
     * @param ResourceType $resourceType
     * @param mixed[]|null $result
     * @return ODataBagContent|null
     * @throws InvalidOperationException
     * @throws \ReflectionException
     * @return ODataBagContent|null
     */
    public static function writeBagValue(ResourceType &$resourceType, ?array $result): ?ODataBagContent
    {
        $typeKind = $resourceType->getResourceTypeKind();
        $kVal     = $typeKind;
        if (!(ResourceTypeKind::PRIMITIVE() == $kVal || ResourceTypeKind::COMPLEX() == $kVal)) {
            $msg = '$bagItemResourceTypeKind != ResourceTypeKind::PRIMITIVE'
                   . ' && $bagItemResourceTypeKind != ResourceTypeKind::COMPLEX';
            throw new InvalidOperationException($msg);
        }
        if (null == $result) {
            return null;
        }
        $bag    = new ODataBagContent();
        $result = array_filter($result);
        foreach ($result as $value) {
            if (ResourceTypeKind::PRIMITIVE() == $kVal) {
                $instance = $resourceType->getInstanceType();
                if (!$instance instanceof IType) {
                    throw new InvalidOperationException(get_class($instance));
                }
                $bag[] = SerialiserLowLevelWriters::primitiveToString($instance, $value);
            } elseif (ResourceTypeKind::COMPLEX() == $kVal) {
                $bag[] = SerialiserLowLevelWriters::writeComplexValue($resourceType, $value);
            }
        }
        return $bag;
    }

    /**
     * @param  ResourceType              $resourceType
     * @param  object                    $result
     * @param  string|null               $propertyName
     * @param  object[]                  $instanceCollection
     * @throws InvalidOperationException
     * @throws \ReflectionException
     * @return ODataPropertyContent
     */
    public static function writeComplexValue(
        ResourceType &$resourceType,
        &$result,
        $propertyName = null,
        array &$instanceCollection = []
    ): ODataPropertyContent {
        if (!is_object($result)) {
            throw new InvalidOperationException('Supplied $customObject must be an object');
        }

        $count = count($instanceCollection);
        for ($i = 0; $i < $count; ++$i) {
            if ($instanceCollection[$i] === $result) {
                throw new InvalidOperationException(
                    Messages::objectModelSerializerLoopsNotAllowedInComplexTypes($propertyName)
                );
            }
        }

        $instanceCollection[$count] = &$result;

        $internalContent    = new ODataPropertyContent([]);
        $resourceProperties = $resourceType->getAllProperties();
        // first up, handle primitive properties
        foreach ($resourceProperties as $prop) {
            $resourceKind           = $prop->getKind();
            $propName               = $prop->getName();
            $internalProperty       = new ODataProperty($propName, null, null);
            if (SerialiserUtilities::isMatchPrimitive($resourceKind->getValue())) {
                $iType = $prop->getInstanceType();
                if (!$iType instanceof IType) {
                    throw new InvalidOperationException(get_class($iType));
                }
                $internalProperty->setTypeName($iType->getFullTypeName());

                $rType = $prop->getResourceType()->getInstanceType();
                if (!$rType instanceof IType) {
                    throw new InvalidOperationException(get_class($rType));
                }


                $value = SerialiserLowLevelWriters::primitiveToString($rType, $result->{$propName});
                $internalProperty->setValue($value);

                $internalContent[$propName] = $internalProperty;
            } elseif (ResourcePropertyKind::COMPLEX_TYPE() == $resourceKind) {
                $rType                      = $prop->getResourceType();
                $internalProperty->setTypeName($rType->getFullName());
                $value                      = SerialiserLowLevelWriters::writeComplexValue(
                    $rType,
                    $result->{$propName},
                    $propName,
                    $instanceCollection
                );
                $internalProperty->setValue($value);

                $internalContent[$propName] = $internalProperty;
            }
        }

        unset($instanceCollection[$count]);
        return $internalContent;
    }


    /**
     * Convert the given primitive value to string.
     * Note: This method will not handle null primitive value.
     *
     * @param IType $type                   Type of the primitive property needing conversion
     * @param mixed $primitiveValue         Primitive value to convert
     *
     * @return string
     */
    public static function primitiveToString(IType &$type, $primitiveValue): string
    {
        // kludge to enable switching on type of $type without getting tripped up by mocks as we would with get_class
        // switch (true) means we unconditionally enter, and then lean on case statements to match given block
        switch (true) {
            case $type instanceof StringType:
                $primitiveValue = strval($primitiveValue);
                $isUTF8         = 'UTF-8' === mb_detect_encoding($primitiveValue);
                $stringValue    = $isUTF8 ? $primitiveValue : utf8_encode($primitiveValue);
                break;
            case $type instanceof Boolean:
                $stringValue = (true === $primitiveValue) ? 'true' : 'false';
                break;
            case $type instanceof Binary:
                $stringValue = base64_encode($primitiveValue);
                break;
            case $type instanceof DateTime && $primitiveValue instanceof \DateTime:
                $stringValue = $primitiveValue->format(\DateTime::ATOM);
                break;
            default:
                $stringValue = strval($primitiveValue);
        }

        return $stringValue;
    }
}
