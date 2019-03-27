<?php

namespace App\Entity;

class UserTag extends Neo4jCore
{
    /**
     * @var int
     * @DataProperty
     */
    private $score;

    /**
     * @var User
     */
    private $user;

    public function __construct()
    {
        parent::__construct(Neo4jCore::NEO4J_RELATION, "HAS_TAG");
    }

    /**
     * @return int|null
     */
    public function getScore(): ?int
    {
        return $this->score;
    }

    /**
     * @param int $score
     * @return UserTag
     */
    public function setScore(int $score): UserTag
    {
        $this->score = $score;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return UserTag
     */
    public function setUser(User $user): UserTag
    {
        $this->user = $user;
        return $this;
    }

}
