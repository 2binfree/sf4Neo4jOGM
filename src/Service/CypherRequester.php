<?php

namespace App\Service;

use App\Entity\Neo4jCore;
use GraphAware\Neo4j\Client\ClientInterface;
use GraphAware\Neo4j\Client\Formatter\Result;
use GraphAware\Neo4j\Client\Formatter\Type\Node;

class CypherRequester
{
    const CREATE_NODE           = "CREATE_NODE";
    const CREATE_RELATIONSHIP   = "CREATE_RELATIONSHIP";
    const MATCH_NODES           = "MATCH_NODES";

    const FIRST_NODE    = "FIRST_NODE";
    const SECOND_NODE   = "SECOND_NODE";
    const RELATIONSHIP  = "RELATIONSHIP";
    const TYPE          = "TYPE";
    const PARAMETERS    = "PARAMETERS";

    /** @var ClientInterface */
    private $client;

    /**
     * CypherRequest constructor.
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @param Neo4jCore $entity
     * @return array
     * @throws \ReflectionException
     */
    public function createNode(Neo4jCore $entity)
    {
        $data[self::FIRST_NODE] = $entity;
        $query = $this->generateQuery(self::CREATE_NODE, $data);
        /** @var Result $result */
        $result = $this->client->run($query);
        return $this->getNodeValues($result);
    }

    /**
     * @param Neo4jCore $from
     * @param Neo4jCore $to
     * @param Neo4jCore|string $relationShip
     * @return array
     * @throws \ReflectionException
     */
    public function createRelationShip(Neo4jCore $from, Neo4jCore $to, $relationShip)
    {
        if (is_string($relationShip)) {
            $type = $relationShip;
            $relationShipEntity = null;
        } else {
            if (is_a($relationShip, Neo4jCore::class)) {
                $type = $relationShip->getNeo4jType();
                $relationShipEntity = $relationShip;
            } else {
                throw new \LogicException("relationShip must be a string (type)");
            }

        }
        $data = [
            self::FIRST_NODE => $from,
            self::SECOND_NODE => $to,
            self::TYPE => $type,
            self::RELATIONSHIP => $relationShipEntity,
        ];
        $query = $this->generateQuery(self::CREATE_RELATIONSHIP, $data);
        /** @var Result $result */
        $result = $this->client->run($query);
        return $this->getNodeValues($result);
    }

    /**
     * @param string $type
     * @param array $parameters
     * @return array|string
     * @throws \ReflectionException
     */
    public function getNodesBy(string $type, array $parameters)
    {
        $data = [
            self::TYPE => $type,
            "fields" => $parameters,
        ];
        $query = $this->generateQuery(self::MATCH_NODES, $data);
        /** @var Result $result */
        $result = $this->client->run($query);
        return $this->getNodeValues($result);
    }

    /**
     * @param Result $result
     * @return string|array
     */
    private function getNodeValues(Result $result)
    {
        /** @var Node $node */
        $node = $result->getRecord()->values()[0];
        if (is_string($node)) {
            return $node;
        }
        return $node->values();
    }

    /**
     * @param $object
     * @return mixed
     */
    private function getClassname($object)
    {
        $name = get_class($object);
        $nameParts = explode("\\", $name);
        return end($nameParts);
    }

    /**
     * @param string $requestType
     * @param array $data
     * @return string
     * @throws \ReflectionException
     */
    private function generateQuery(string $requestType, array $data)
    {
        switch ($requestType) {
            case self::CREATE_NODE: // FIRST_NODE
                // CREATE (n:Person { name: 'Andy', title: 'Developer' }) return n
                $entity = $this->getNode($data, self::FIRST_NODE);
                $type = $this->getClassname($entity);
                $fields = $this->generateFieldProperties($entity);
                if (!empty($fields)) {
                    $fields = "{" . $fields . "}";
                }
                $query = "CREATE (n:" . $type;
                $query .= $fields;
                $query .= ") return n";
                break;
            case self::CREATE_RELATIONSHIP: // FIRST_NODE, SECOND_NODE, TYPE, RELATIONSHIP
                //MATCH (a:Person),(b:Person) WHERE a.name = 'A' AND b.name = 'B' CREATE (a)-[r:RELTYPE]->(b) RETURN type(r)
                /** @var Neo4jCore $from */
                $from = $this->getNode($data, self::FIRST_NODE);
                /** @var Neo4jCore $to */
                $to = $this->getNode($data, self::SECOND_NODE);
                $relation = $this->getNode($data, self::RELATIONSHIP);
                $fields = $this->generateFieldProperties($relation);
                if (!empty($fields)) {
                    $fields = "{" . $fields . "}";
                }
                $query = "MATCH (a:" . $this->getClassname($from) . "), (b:" . $this->getClassname($to) . ") ";
                $query .= "WHERE a.nuid = '" . $from->getNuid() . "' AND b.nuid = '" . $to->getNuid() . "' ";
                $query .= "CREATE (a)-[r:" . $data[self::TYPE] . $fields . "]->(b) ";
                $query .= "RETURN type(r)";
                break;
            case self::MATCH_NODES: // TYPE, PARAMETERS
                //MATCH (a:Person) WHERE a.name = 'A' AND a.email = 'B' return a
                $type = $data[self::TYPE];
                $query = "MATCH (n:" . $type . ") ";
                $query .= "WHERE " . $this->generateFieldProperties($data[self::PARAMETERS], "n", "AND");
                break;
            default:
                throw new \LogicException("Unknown query type");
        }
        return $query;
    }

    /**
     * @param Neo4jCore|array $data
     * @param string $prefix
     * @param string $separator
     * @return string
     * @throws \ReflectionException
     */
    private function generateFieldProperties($data, $prefix = "", $separator = ",")
    {
        $query = "";
        if (!empty($data)) {
            if (is_a($data, Neo4jCore::class)) {
                $fields = $data->toArray(true);
                $fields["nuid"] = $data->getNuid();
            } else {
                $fields = $data;
            }
            $currentEntry = 1;
            $lastEntry = count($fields);
            foreach ($fields as $name => $value) {
                if (!empty($prefix)) {
                    $field = $prefix . "." . $name;
                } else {
                    $field = $name;
                }
                $query .= $field . ": '" . $value . "'";
                if ($currentEntry++ < $lastEntry) {
                    $query .= " " . $separator . " ";
                }
            }
        }
        return $query;
    }

    /**
     * @param array $data
     * @param $element
     * @return mixed
     */
    private function getNode($data, $element)
    {
        if (!isset($data[$element])) {
            return null;
        }
        $entity = $data[$element];
        if (!is_a($entity, Neo4jCore::class)) {
            throw new \LogicException("Object must be type of Neo4jCore");
        }
        return $entity;
    }

    /**
     * @return ClientInterface
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }


}
