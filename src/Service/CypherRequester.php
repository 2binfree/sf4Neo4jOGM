<?php

namespace App\Service;

use App\Entity\Neo4jCore;
use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Common\Result\Record;
use GraphAware\Neo4j\Client\ClientInterface;
use GraphAware\Bolt\Result\Result;
use GraphAware\Neo4j\Client\Formatter\Type\Node;
use GraphAware\Neo4j\Client\Stack;

class CypherRequester
{
    const CREATE_NODE           = "CREATE_NODE";
    const CREATE_RELATIONSHIP   = "CREATE_RELATIONSHIP";
    const MATCH_NODES           = "MATCH_NODES";

    const MODE_SINGLE   = "MODE_SINGLE";
    const MODE_BATCH    = "MODE_BATCH";

    const TRANSACTION_ON    = "ON";
    const TRANSACTION_OFF   = "OFF";

    const START_NODE    = "START_NODE";
    const END_NODE      = "END_NODE";
    const RELATIONSHIP  = "RELATIONSHIP";
    const TYPE          = "TYPE";
    const PARAMETERS    = "PARAMETERS";

    /** @var ClientInterface */
    private $client;

    /** @var string */
    private $mode;

    /** @var string */
    private $transactionStatus;

    /** @var Stack */
    private $stack;

    /**
     * CypherRequest constructor.
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
        $this->mode = self::MODE_SINGLE;
        $this->transactionStatus = self::TRANSACTION_OFF;
        $this->stack = $client->stack();
    }

    /**
     * @param Neo4jCore $entity
     * @return array
     * @throws \ReflectionException
     */
    public function createNode(Neo4jCore $entity)
    {
        $data[self::START_NODE] = $entity;
        $query = $this->generateQuery(self::CREATE_NODE, $data);
        /** @var Result $result */
        $result = $this->exec($query);
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
            self::START_NODE => $from,
            self::END_NODE => $to,
            self::TYPE => $type,
            self::RELATIONSHIP => $relationShipEntity,
        ];
        $query = $this->generateQuery(self::CREATE_RELATIONSHIP, $data);
        /** @var Result $result */
        $result = $this->exec($query);
        return $this->getNodeValues($result);
    }

    /**
     * @param string $entityClass
     * @param array $parameters
     * @return array|string
     * @throws \ReflectionException
     */
    public function getNodesBy(string $entityClass, array $parameters)
    {
        /** @var Neo4jCore $object */
        $object = new $entityClass();
        if (!is_a($object, Neo4jCore::class)) {
            throw new \LogicException("entity must be an instance of Neo4jCore class");
        }
        $type = $object->getNeo4jType();
        $data = [
            self::TYPE          => $type,
            self::PARAMETERS    => $parameters,
        ];
        $query = $this->generateQuery(self::MATCH_NODES, $data);
        /** @var Result $result */
        $result = $this->client->run($query);
        return $this->hydrateCollection($entityClass, $result->getRecords());
    }

    /**
     * @param Result|bool $result
     * @return string|array|bool
     */
    private function getNodeValues($result)
    {
        if (self::MODE_BATCH === $this->mode) {
            return true;
        }
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
            case self::CREATE_NODE: // START_NODE
                // CREATE (n:Person { name: 'Andy', title: 'Developer' }) return n
                $entity = $this->getNode($data, self::START_NODE);
                $type = $this->getClassname($entity);
                $fields = $this->generateFieldProperties($entity);
                if (!empty($fields)) {
                    $fields = "{" . $fields . "}";
                }
                $query = "CREATE (n:" . $type . $fields . ") ";
                if (self::MODE_SINGLE === $this->mode) {
                    $query .= "return n";
                }
                break;
            case self::CREATE_RELATIONSHIP: // START_NODE, END_NODE, TYPE, RELATIONSHIP
                //MATCH (a:Person),(b:Person) WHERE a.name = 'A' AND b.name = 'B' CREATE (a)-[r:RELTYPE]->(b) RETURN type(r)
                /** @var Neo4jCore $from */
                $from = $this->getNode($data, self::START_NODE);
                /** @var Neo4jCore $to */
                $to = $this->getNode($data, self::END_NODE);
                $relation = $this->getNode($data, self::RELATIONSHIP);
                $fields = $this->generateFieldProperties($relation);
                if (!empty($fields)) {
                    $fields = "{" . $fields . "}";
                }
                $query = "MATCH (a:" . $this->getClassname($from) . "), (b:" . $this->getClassname($to) . ") ";
                $query .= "WHERE a.nuid = '" . $from->getNuid() . "' AND b.nuid = '" . $to->getNuid() . "' ";
                $query .= "CREATE (a)-[r:" . $data[self::TYPE] . $fields . "]->(b) ";
                if (self::MODE_SINGLE === $this->mode) {
                    $query .= "RETURN type(r)";
                }
                break;
            case self::MATCH_NODES: // TYPE, PARAMETERS
                //MATCH (a:Person) WHERE a.name = 'A' AND a.email = 'B' return a
                $type = $data[self::TYPE];
                $query = "MATCH (n:" . $type . ") ";
                $parameters = $this->generateFieldProperties($data[self::PARAMETERS], "n", "AND");
                if (!empty($parameters)) {
                    $query .= "WHERE " . $parameters;
                }
                $query .= "RETURN n";
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
                if (is_numeric($value)) {
                    $query .= $field . ": " . $value;
                } else {
                    $query .= $field . ": '" . $value . "'";
                }
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

    /**
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     * @return CypherRequester
     */
    public function setMode(string $mode): CypherRequester
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionStatus(): string
    {
        return $this->transactionStatus;
    }

    /**
     * @param string $transactionStatus
     * @return CypherRequester
     */
    public function setTransactionStatus(string $transactionStatus): CypherRequester
    {
        $this->transactionStatus = $transactionStatus;
        return $this;
    }

    /**
     * @param $query
     * @return \GraphAware\Common\Result\Result | bool
     */
    private function exec($query)
    {
        if (self::MODE_SINGLE == $this->mode) {
            $result = $this->client->run($query);
            return $result;
        } else {
            $this->stack->push($query);
            return true;
        }
    }

    /**
     * @return \GraphAware\Neo4j\Client\Result\ResultCollection|null
     * @throws \GraphAware\Neo4j\Client\Exception\Neo4jException
     */
    public function flush()
    {
        if (0 < $this->stack->size()) {
            return $this->client->runStack($this->stack);
        }
        throw new \LogicException("client stack is empty");
    }

    /**
     * @param string $entityClass
     * @param $records
     * @return ArrayCollection
     * @throws \ReflectionException
     */
    private function hydrateCollection(string $entityClass, $records)
    {
        $collection = new ArrayCollection();
        /** @var Record $record */
        foreach ($records as $record) {
            /** @var Neo4jCore $object */
            $object = new $entityClass();
            /** @var Node $values */
            $values = $record->values()[0];
            $object->hydrate($values->values());
            $collection->add($object);
        }
        return $collection;
    }
}
