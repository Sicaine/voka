<?php
namespace Voka\VokaBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Voka\VokaBundle\Document\Alias;
use Voka\VokaBundle\Document\Country;
use Voka\VokaBundle\Document\Property;
use Voka\VokaBundle\Document\Wikibase;
use Voka\VokaBundle\Entity\VokaCountryCard;

class GeoDataDeNormalizeCommand extends ContainerAwareCommand{

    /** @var OutputInterface */
    private $output;

    /** @var  DocumentManager */
    private $dm;

    protected function configure(){
        $this->setName('voka:generate:geodata');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $this->output = $output;

        $output->writeln('Read all countries from db');

        $this->dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');

        /** @var Country[] $result */
        $result = $this->dm->getRepository('VokaBundle:Country')->findBy([], ['id' => 'asc']);

        /** @var EntityManager $ormEm */
        $ormEm = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        foreach($result as $country){
            $output->writeln('Found: '.$country->getId());

            $label = $this->findLabelFromCountry($country);
            $claims = $this->findClaimsFromCountry($country);

            $output->writeln(' '.$label);

            $result = $ormEm->getRepository('Voka\VokaBundle\Entity\VokaCountryCard')->find($country->getId());
            if(count($result) != 1){
                $countryCard = new VokaCountryCard();
                $countryCard->setId($country->getId());
            } else {
                $countryCard = $result;
            }


            if(array_key_exists('P17', $claims)) {
                $countryCard->setName($claims['P17']);
            }

            $ormEm->persist($countryCard);
        }
        $ormEm->flush();
    }

    private function findClaimsFromCountry(Country $country){
        $claims = [];

        foreach($country->getClaims() as $key=>$value){
            $value[0];

            if($value[0]['mainsnak']['snaktype'] == 'value'){
                $property = $value[0]['mainsnak']['property'];
                $this->output->writeln(' found property: '.$property);
                if($value[0]['mainsnak']['datatype'] == 'value'){
                    $value = $value[0]['mainsnak']['datavalue']['value'];
                    $claims[$property] = $value;
                } else if($value[0]['mainsnak']['datatype'] == 'wikibase-item'){
                    $this->output->write(' wikidata: ');
                    $wikibaseId = $value[0]['mainsnak']['datavalue']['value']['numeric-id'];
                    /** @var Wikibase[] $result */
                    $result = $this->dm->getRepository('VokaBundle:Wikibase')->findBy(['_id' => 'Q'.$wikibaseId], ['id' => 'asc']);

                    if(count($result) != 1){
                        continue;
                    }
                    $json = json_decode($result[0]->getData());

                    if(property_exists($json->labels, 'en')) {
                        $this->output->writeln(' value: '.$json->labels->en->value);
                        $claims[$property] = $json->labels->en->value;
                    } else {
                        continue;
                    }
                }

                $this->output->writeln(' type: '.$value[0]['mainsnak']['datatype']);
            }
        }

        return $claims;
    }

    private function findLabelFromCountry(Country $country){
        foreach($country->getLabels() as $key=>$value){
            // stop when found ger
            if($value['language'] == 'de') {
                return $value['value'];
            }
        }

        return '';
    }

} 