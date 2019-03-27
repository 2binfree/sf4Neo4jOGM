<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Entity\User;
use App\Entity\UserTag;
use App\Service\CypherRequester;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TestController
 * @package App\Controller
 * @Route("/neo")
 */
class TestController extends AbstractController
{
    private function clean(CypherRequester $manager)
    {
        $client = $manager->getClient();
        $result = $client->run("MATCH (n) OPTIONAL MATCH (n)-[r]-() DELETE n,r");
        $client->run("CREATE CONSTRAINT ON (u:User) ASSERT u.nuid IS UNIQUE");
    }

    /**
     * @param CypherRequester $manager
     * @return Response
     * @Route("/create", name="create")
     */
    public function create(CypherRequester $manager)
    {
        $tags = ["dev", "gaming", "cooking"];
        try {
            $this->clean($manager);
            $manager->setMode(CypherRequester::MODE_BATCH);
            $user = new User();
            $user->setFirstName("Laurent");
            $user->setLastName("Aubertin");
            $user->setEmail("laurent@superconnectr.com");
            $manager->createNode($user);
            foreach ($tags as $tagName) {
                $tag = new Tag();
                $tag->setName($tagName);
                $userTag = new UserTag();
                $userTag->setScore(rand(1, 5));
                $manager->createNode($tag);
                $manager->createRelationShip($user, $tag, $userTag);
                unset($tag);
                unset($userTag);
            }
            $manager->flush();
            return new Response("Done");
        } catch (Exception $e) {
            return new Response($e->getMessage());
        }
    }

    /**
     * @param CypherRequester $manager
     * @return Response
     * @throws \ReflectionException
     * @Route("/read")
     */
    public function read(CypherRequester $manager)
    {
        $data = $manager->getNodesBy(Tag::class, []);
        var_dump($data);
        return new Response("Done");
    }
}
