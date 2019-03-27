<?php

namespace App\Entity;

use ToBinFree\Hydrator\Hydrator;

class Neo4jCore
{
    use Hydrator;

    const NEO4J_NODE = "Node";
    const NEO4J_RELATION = "Relation";

    /** @var string */
    private $nuid;

    /** @var string */
    private $neo4jType;

    /** @var string */
    private $neo4jObject;

    /**
     * Neo4jCore constructor.
     * @param string $objectType
     * @param string|null $neo4jType
     */
    public function __construct(string $objectType, string $neo4jType)
    {
        if (empty($objectType || empty($neo4jType))) {
            throw new \LogicException("You must define if entity is a node or a relationShip, and his type");
        }
        if ($objectType !== self::NEO4J_RELATION && $objectType !== self::NEO4J_NODE) {
            throw new \LogicException("neo4j object type " . $objectType . " is unknown");
        }
        $this->nuid = uniqid($neo4jType . "_" . $objectType . "_", true);
        $this->neo4jType = $neo4jType;
        $this->neo4jObject = $objectType;
    }

    /**
     * @return string
     */
    public function getNuid()
    {
        return $this->nuid;
    }

    /**
     * @return string
     */
    public function getNeo4jType(): string
    {
        return $this->neo4jType;
    }

    /**
     * @return string
     */
    public function getNeo4jObject(): string
    {
        return $this->neo4jObject;
    }


}
