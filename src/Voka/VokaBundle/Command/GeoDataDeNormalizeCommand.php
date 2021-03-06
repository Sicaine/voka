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
use Voka\VokaBundle\Document\WikibaseImage;
use Voka\VokaBundle\Entity\VokaCountryCard;

class GeoDataDeNormalizeCommand extends ContainerAwareCommand{

    /** @var OutputInterface */
    private $output;

    /** @var  DocumentManager */
    private $dm;

    protected function configure(){
        $this->setName('voka:generate:geodata');
        $this->addOption('country', 'Q', InputArgument::OPTIONAL, 'Specify Country Code Q<XX..>');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $qCode = $input->getOption('country');
        $this->output = $output;

        $output->writeln('Read all countries from db');

        $this->dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');

        $qCodeSearchCriteria = [];
        if($qCode !== null) {
            $qCodeSearchCriteria['id'] = $qCode;
        }

        /** @var Country[] $result */
        $result = $this->dm->getRepository('VokaBundle:Country')->findBy($qCodeSearchCriteria, ['id' => 'asc']);

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

            $countryCard->setLabel($label);

            if(array_key_exists('P17', $claims)) {
                $countryCard->setName($claims['P17']);
            }

            if(array_key_exists('P36', $claims)) {
                $countryCard->setCapital($claims['P36']);
            }

            if(array_key_exists('P30', $claims)) {
                $countryCard->setContinent($claims['P30']);
            }

            if(array_key_exists('P1082', $claims)) {
                $countryCard->setPopulation($claims['P1082']);
            }

            if(array_key_exists('P78', $claims)) {
                $countryCard->setTopLevelDomain($claims['P78']);
            }

            if(array_key_exists('P122', $claims)) {
                $countryCard->setBasicFormOfGovernment($claims['P122']);
            }

            if(array_key_exists('P610', $claims)) {
                $countryCard->setHighestPoint($claims['P610']);
            }

            if(array_key_exists('P163', $claims)) {
                $countryCard->setFlag($claims['P163']);
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
                $this->output->write('. found property: '.$property);
                if($value[0]['mainsnak']['datatype'] == 'value'){
                    $value = $value[0]['mainsnak']['datavalue']['value'];
                    $claims[$property] = $value;
                } else if($value[0]['mainsnak']['datatype'] == 'wikibase-item'){
                    $this->output->write(' wikidata: ');
                    $latestWikiBaseItem = $this->findLatestWikibaseItem($value);
                    $wikibaseId = $latestWikiBaseItem['mainsnak']['datavalue']['value']['numeric-id'];

                    // P163 == flag
                    if($property === 'P163') {
                        /** @var WikibaseImage[] $result */
                        $result = $this->dm->getRepository('VokaBundle:WikibaseImage')->findBy(
                            ['_id' => 'Q' . $wikibaseId],
                            ['id' => 'asc']
                        );

                        if(count($result) != 1){
                            continue;
                        }
                        $claims[$property] = $result[0]->getData();
                    } else {

                        /** @var Wikibase[] $result */
                        $result = $this->dm->getRepository('VokaBundle:Wikibase')->findBy(
                            ['_id' => 'Q' . $wikibaseId],
                            ['id' => 'asc']
                        );

                        if (count($result) != 1) {
                            continue;
                        }
                        $json = json_decode($result[0]->getData());

                        if (property_exists($json->labels, 'en')) {
                            $this->output->write(' value: ' . $json->labels->en->value);
                            $claims[$property] = $json->labels->en->value;
                        } else {
                            continue;
                        }
                    }
                }

                $this->output->write(' type: '.$value[0]['mainsnak']['datatype']);
            }
        }

        $this->output->writeln(' . claims done.');
        return $claims;
    }

    private function findLatestWikibaseItem($claim){
        $latestTime = null;
        $latestClaim = null;

        foreach($claim as $subclaim) {

            if ($latestClaim === null) {
                $latestClaim = $subclaim;
            }
            if (is_array($subclaim) &&
                array_key_exists('qualifiers', $subclaim) &&
                array_key_exists('P580', $subclaim['qualifiers']) &&
                count($subclaim['qualifiers']['P580']) > 0 &&
                array_key_exists('datavalue', $subclaim['qualifiers']['P580'][0])
            ) {
                if ($latestTime == null) {
                    $latestTime = new \DateTime(substr($subclaim['qualifiers']['P580'][0]['datavalue']['value']['time'], 8));
                    $latestClaim = $subclaim;
                    $this->output->writeln($latestTime->format('Y-m-d H:i:s'));
                }

                $time = new \DateTime(substr($subclaim['qualifiers']['P580'][0]['datavalue']['value']['time'], 8));

                if ($time > $latestTime) {
                    $latestTime = new \DateTime(substr($subclaim['qualifiers']['P580'][0]['datavalue']['value']['time'], 8));
                    $latestClaim = $subclaim;
                }
            }
        }

        return $latestClaim;
    }

    private function findLabelFromCountry(Country $country){
        $label = '';
        foreach($country->getLabels() as $key=>$value){
            if(empty($label) && $value['language'] === 'en') {
                $label = $value['value'];
            }
            // stop when found ger
            if($value['language'] === 'de') {
                $label = $value['value'];
            }
        }

        return $label;
    }

} 