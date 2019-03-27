<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * User
 */
class User extends Neo4jCore
{
    public function __construct()
    {
        parent::__construct(Neo4jCore::NEO4J_NODE, "User");
        $this->tags = new ArrayCollection();
    }

    /**
     * Relationship type="AS_TAG", direction="OUTGOING", collection=true, targetEntity="Tag", mappedBy="user"
     * @var ArrayCollection
     */
    private $tags;

    /**
     * @var string $email
     * @DataProperty
     */
    private $email;

    /**
     * @var string
     * @DataProperty
     */
    private $firstName;

    /**
     * @var string
     * @DataProperty
     */
    private $lastName;

    /**
     * @var string
     * @DataProperty
     */
    private $company;

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return User
     */
    public function setEmail(string $email): User
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     * @return User
     */
    public function setFirstName(string $firstName): User
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     * @return User
     */
    public function setLastName(string $lastName): User
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCompany(): ?string
    {
        return $this->company;
    }

    /**
     * @param string $company
     * @return User
     */
    public function setCompany(string $company): User
    {
        $this->company = $company;
        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getTags()
    {
        return $this->tags;
    }
}
