<?php

namespace Voka\VokaBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller {

    public function indexAction() {
        return $this->render('VokaBundle:Default:index.html.twig');
    }

    public function getVokabelAction() {
        /** @var EntityManager $ormEm */
        $ormEm = $this->get('doctrine.orm.default_entity_manager');

        $rsm = new ResultSetMappingBuilder($ormEm);
        $rsm->addRootEntityFromClassMetadata('Voka\VokaBundle\Entity\VokaCountryCard', 'v');

        $result = $ormEm->createNativeQuery(
            'SELECT v.id, v.name, v.capital, v.continent, v.population, v.label
             FROM VokaCountryCard as v
             WHERE name is not null AND capital is not null ORDER BY RAND() LIMIT 1',
            $rsm
        )->getResult();

        $serializer = $this->get('jms_serializer');
        $blub = $serializer->serialize($result[0], 'json');
        //var_dump($blub);
        return new Response($blub);
    }
}
