<?php

namespace App\Entity;

/**
 * Tag
 */
class Tag extends Neo4jCore
{
    /**
     * Tag constructor.
     */
    public function __construct()
    {
        parent::__construct(Neo4jCore::NEO4J_NODE, "Tag");
    }

    /**
     * @var User
     */
    private $user;

    /**
     * @var string $email
     * @DataProperty
     */
    private $name;

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return Tag
     */
    public function setUser(User $user): Tag
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Tag
     */
    public function setName(string $name): Tag
    {
        $this->name = $name;
        return $this;
    }
}
