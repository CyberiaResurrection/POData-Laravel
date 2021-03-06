<?php declare(strict_types=1);

namespace AlgoWeb\PODataLaravel\Models;

use AlgoWeb\PODataLaravel\Models\ObjectMap\Entities\Associations\AssociationMonomorphic;
use AlgoWeb\PODataLaravel\Models\ObjectMap\Entities\Associations\AssociationStubBase;
use AlgoWeb\PODataLaravel\Models\ObjectMap\Entities\Associations\AssociationStubMonomorphic;
use AlgoWeb\PODataLaravel\Models\ObjectMap\Entities\Associations\AssociationStubPolymorphic;
use AlgoWeb\PODataLaravel\Models\ObjectMap\Entities\Associations\AssociationStubRelationType;
use AlgoWeb\PODataLaravel\Models\ObjectMap\Entities\EntityGubbins;
use Illuminate\Support\Str;
use POData\Common\InvalidOperationException;

class MetadataGubbinsHolder
{
    protected $relations  = [];
    protected $knownSides = [];

    /**
     * Add entity to holder.
     *
     * @param  EntityGubbins             $entity
     * @throws InvalidOperationException
     */
    public function addEntity(EntityGubbins $entity)
    {
        $className = $entity->getClassName();
        if (array_key_exists($className, $this->relations)) {
            $msg = $className . ' already added';
            throw new \InvalidArgumentException($msg);
        }
        $this->relations[$className]  = $entity;
        $this->knownSides[$className] = [];
        foreach ($entity->getStubs() as $relName => $stub) {
            if ($stub instanceof AssociationStubPolymorphic && $stub->isKnownSide()) {
                $this->knownSides[$className][$relName] = $stub;
            }
        }
    }

    public function getRelationsByRelationName($className, $relName)
    {
        $this->checkClassExists($className);

        $rels = $this->relations[$className];

        if (!array_key_exists($relName, $rels->getStubs())) {
            $msg = 'Relation ' . $relName . ' not registered on ' . $className;
            throw new \InvalidArgumentException($msg);
        }
        /** @var AssociationStubBase $stub */
        $stub     = $rels->getStubs()[$relName];
        $targType = $stub->getTargType();
        if (!array_key_exists($targType, $this->relations)) {
            return [];
        }
        $targRel = $this->relations[$targType];
        // now dig out compatible stubs on target type
        $targStubs = $targRel->getStubs();
        $relStubs  = [];
        foreach ($targStubs as $targStub) {
            if ($stub->isCompatible($targStub)) {
                $relStubs[] = $targStub;
            }
        }
        return $relStubs;
    }

    /**
     * @param  string                    $className
     * @throws InvalidOperationException
     * @return array
     */
    public function getRelationsByClass($className)
    {
        $this->checkClassExists($className);

        $rels  = $this->relations[$className];
        $stubs = $rels->getStubs();

        $associations = [];
        foreach ($stubs as $relName => $stub) {
            $others = $this->getRelationsByRelationName($className, $relName);
            if ($stub instanceof AssociationStubMonomorphic) {
                $msg = 'Monomorphic relation stub on ' . $className . ' ' . $relName
                       . ' should point to at most 1 other stub';
                if (!(1 >= count($others))) {
                    throw new InvalidOperationException($msg);
                }
            }
            if (1 === count($others)) {
                $others = $others[0];
                $assoc  = new AssociationMonomorphic();
                $first  = -1 === $stub->compare($others);
                $assoc->setFirst($first ? $stub : $others);
                $assoc->setLast($first ? $others : $stub);
                if (!$assoc->isOk()) {
                    throw new InvalidOperationException('');
                }
                $associations[] = $assoc;
            }
        }
        return $associations;
    }

    /**
     * @throws InvalidOperationException
     * @return array
     */
    public function getRelations()
    {
        $classNames = array_keys($this->relations);

        $associations = $this->buildRelationAssociations($classNames);

        $unknowns = $this->buildUnknownSides();

        $monoAssoc = [];
        $polyAssoc = [];
        foreach ($associations as $assoc) {
            if ($assoc->getFirst() instanceof AssociationStubMonomorphic) {
                $monoAssoc[] = $assoc;
                continue;
            }
            // monomorphic associations are dealt with, now for the polymorphic associations - they're a mite trickier
            $firstKnown = $assoc->getFirst()->isKnownSide();
            /** @var AssociationStubPolymorphic $known */
            $known = $firstKnown ? $assoc->getFirst() : $assoc->getLast();
            /** @var AssociationStubPolymorphic $unknown */
            $unknown                          = $firstKnown ? $assoc->getLast() : $assoc->getFirst();
            $className                        = $known->getBaseType();
            $relName                          = $known->getRelationName();
            $unknowns[$className][$relName][] = $unknown;
        }

        foreach ($this->knownSides as $knownType => $knownDeets) {
            foreach (array_keys($knownDeets) as $key) {
                /** @var AssociationStubPolymorphic[] $lastCandidates */
                $lastCandidates = $unknowns[$knownType][$key];
                if (0 == count($lastCandidates)) {
                    continue;
                }
                foreach ($lastCandidates as $lc) {
                    /** @var AssociationStubPolymorphic $stub */
                    $stub            = clone $this->knownSides[$knownType][$key];
                    $isMulti         = ($stub->getMultiplicity() == AssociationStubRelationType::MANY());
                    $relPolyTypeName = substr($lc->getBaseType(), strrpos($lc->getBaseType(), '\\')+1);
                    $relPolyTypeName = Str::plural($relPolyTypeName, $isMulti ? 2 : 1);
                    $stub->setRelationName($stub->getRelationName() . '_' . $relPolyTypeName);
                    $assoc = new AssociationMonomorphic();
                    $first = -1 === $stub->compare($lc);
                    $assoc->setFirst($first ? $stub : $lc);
                    $assoc->setLast($first ? $lc : $stub);
                    if (!$assoc->isOk()) {
                        throw new InvalidOperationException('');
                    }
                    $polyAssoc[] = $assoc;
                }
            }
        }
        $result = array_merge($monoAssoc, $polyAssoc);
        return $result;
    }

    public function hasClass($className)
    {
        return array_key_exists($className, $this->relations);
    }

    /**
     * @param string $className
     */
    protected function checkClassExists(string $className)
    {
        if (!$this->hasClass($className)) {
            $msg = $className . ' does not exist in holder';
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * @param  array                     $classNames
     * @throws InvalidOperationException
     * @return array
     */
    protected function buildRelationAssociations(array $classNames)
    {
        $associations = [];

        foreach ($classNames as $class) {
            $rawAssoc = $this->getRelationsByClass($class);
            foreach ($rawAssoc as $raw) {
                if (!in_array($raw, $associations)) {
                    $associations[] = $raw;
                }
            }
        }
        return $associations;
    }

    /**
     * @return array
     */
    protected function buildUnknownSides()
    {
        $unknowns = [];
        foreach ($this->knownSides as $knownType => $knownDeets) {
            $unknowns[$knownType] = [];
            foreach (array_keys($knownDeets) as $key) {
                $unknowns[$knownType][$key] = [];
            }
        }
        return $unknowns;
    }
}
