<?php
namespace Voka\VokaBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Voka\VokaBundle\Document\Alias;
use Voka\VokaBundle\Document\Country;
use Voka\VokaBundle\Document\Property;
use Voka\VokaBundle\Document\Wikibase;
use Voka\VokaBundle\Document\WikibaseImage;

class GeoDataImporterCommand extends ContainerAwareCommand{

    private $countries = [];
    private $wikibaseItems = [];
    private $claimsPerCountry = [];
    private $claims = [];
    private $wikibaseFlagImages = [];
    const LOCAL_DEBUG = false;
    const ONLY_ONE_OBJ = false;

    /** @var OutputInterface */
    private $output;

    protected function configure(){
        $this->setName('voka:import:geodata');
        $this->addOption('country', 'Q', InputArgument::OPTIONAL, 'Specify Country Code Q<XX..> or multiply with Q<XX..>,Q</XX..>');
        $this->addOption('offline', 'o', InputArgument::OPTIONAL, 'Offline mode, will use static data - usefull for debugging');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $qCode = $input->getOption('country');
        $offlineMode = $input->getOption('offline');
        $this->output = $output;

        if($offlineMode && is_null($qCode)){
            $output->writeln('offline mode and qCode can\'t be used togehter. qCode will be ignored');
        }

        $output->writeln('Retrieve countries');
        if($offlineMode) {
            foreach(json_decode($this->testdata) as $key=>$value){
                $this->countries['Q33'] = $value;
            }
        } else {
            $this->retrieveCountries($qCode);
        }

        $output->writeln('Write countries to db');

        $this->storeAllCollectedCountries();

        $this->storeAllCollectedClaims();

        $this->storeAllCollectedWikibaseItems();

        $this->storeAllCollectedWikibaseFlagItems();


        $output->writeln("Done");
    }

    private function storeAllCollectedCountries(){

        $this->output->write('Start saving all countries ');

        $dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');

        foreach($this->countries as $key=>$value){
            $this->output->write('.');
            // only suspect a full country was loaded when following true:
            if(!is_object($value) || !property_exists($value, 'title') ){
                continue;
            }

            $doc = new Country();
            $doc->setId($value->id);
            $doc->setType($value->type);
            $doc->setModified($value->modified);
            $doc->setTitle($value->title);

            if(property_exists($value, 'aliases')) {
                $doc->setAliases((array)$value->aliases);
            }

            $doc->setLabels((array)$value->labels);
            if(property_exists($value, 'descriptions')) {
                $doc->setDescriptions((array)$value->descriptions);
            }
            $doc->setClaims((array)$value->claims);
            $doc->setSitelinks((array)$value->sitelinks);
            $dm->persist($doc);

        }
        $this->output->write(' flushing');
        $dm->flush();
        $this->output->write(' done.');

        $this->output->writeln(' finished');
    }

    private function storeAllCollectedClaims(){
        $this->output->writeln('Start saving all claims');

        $dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');

        foreach($this->claims as $key=>$value){
            $this->output->write('.');

            $doc = new Property();
            $doc->setId($value->id);
            $doc->setData(json_encode($value));
            $dm->persist($doc);
        }

        $this->output->write(' flushing');
        $dm->flush();
        $this->output->writeln(' done.');

        $this->output->writeln(' finished');
    }

    private function storeAllCollectedWikibaseItems(){
        $this->output->writeln('Start saving all wikidata');

        $dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');

        foreach($this->wikibaseItems as $key=>$value){
            $this->output->write('.');

            $doc = new Wikibase();
            $doc->setId($value->id);
            $doc->setData(json_encode($value));
            $dm->persist($doc);
        }

        $this->output->write(' flushing');
        $dm->flush();
        $this->output->writeln(' done.');

        $this->output->writeln(' finished');
    }

    private function storeAllCollectedWikibaseFlagItems(){
        $this->output->writeln('Start saving all wikidataFlags');

        $dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');

        foreach($this->wikibaseFlagImages as $key=>$value){
            $this->output->write('.');

            $doc = new WikibaseImage();
            $doc->setId($key);
            $doc->setData($value);
            $dm->persist($doc);
        }

        $this->output->write(' flushing');
        $dm->flush();
        $this->output->writeln(' done.');

        $this->output->writeln(' finished');
    }

    private function retrieveCountries($qCode = null){

        if($qCode !== null){
            if(strpos($qCode, ',') > 0) {
                $qCodes = preg_split('/,/', $qCode);
                foreach($qCodes as $entry){
                    $this->countries[$entry] = [];
                }
            } else {
                $this->countries[$qCode] = [];
                $this->output->writeln('Created QCode entry for: ' . $qCode);
            }
        } else {
            $this->output->writeln('Retrieving JSON Data from wmflabs');

            $content = $this->getRAWDataFromUrl('http://wdq.wmflabs.org/api?q=TREE[6256][][31]');

            $this->output->writeln('... retrieved.');

            $this->output->writeln('Start decode JSON');
            $result = json_decode($content);
            $this->output->writeln('finished decode JSON');

            $this->countries = [];

            foreach ($result->items as $item) {
                $this->countries['Q' . $item] = [];
            }
        }

        $ids = [];

        if(self::ONLY_ONE_OBJ){
            foreach ($this->countries as $key => $value) {
                $ids[] = $key;
                $this->retrieveEntityDataAndClaims($ids);
                unset($ids);
                break;
            }
        } else {
            $i = 1;
            foreach ($this->countries as $key => $value) {
                $ids[] = $key;
                if ($i % 50 === 0) {
                    $this->retrieveEntityDataAndClaims($ids);
                    unset($ids);
                    $ids = [];
                    $i = 0;
                }
                $i++;
            }
            if (count($ids) > 0) {
                $this->retrieveEntityDataAndClaims($ids);
            }
        }


    }

    private function getRAWDataFromUrl($url) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private function retrieveEntityDataAndClaims($ids){
        $idString = implode($ids, '|');
        $this->output->writeln('retrieve EntityData for: '.$idString);
        $result = $this->getRAWDataFromUrl('https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.$idString);
        $entities = json_decode($result);

        foreach($entities->entities as $key=>$value){
            $this->output->write('.');
            $this->countries[$key] = $value;

            $claims = $this->retrieveClaims($key);
            $claims = $claims->claims;
            $this->claimsPerCountry[$key] = $claims;


            foreach($claims as $claim){
                $latestTime = null;
                $latestClaim = null;
                // check for qualifier P580 = time -> example germany had capital bonn and has now berlin. look for freshest claim
                if(is_array($claim)) {
                    foreach ($claim as $subclaim) {
                        if($latestClaim === null){
                            $latestClaim = $subclaim;
                        }
                        if(
                            property_exists($subclaim, 'qualifiers') &&
                            property_exists($subclaim->qualifiers, 'P580') &&
                            count($subclaim->qualifiers->P580) > 0 &&
                            property_exists($subclaim->qualifiers->P580[0], 'datavalue')
                        ){
                            if($latestTime == null){
                                $latestTime = new \DateTime(substr($subclaim->qualifiers->P580[0]->datavalue->value->time, 8));
                                $latestClaim = $subclaim;
                                $this->output->writeln($latestTime->format('Y-m-d H:i:s'));
                            }

                            $time = new \DateTime(substr($subclaim->qualifiers->P580[0]->datavalue->value->time, 8));

                            if($time > $latestTime) {
                                $latestTime = new \DateTime(substr($subclaim->qualifiers->P580[0]->datavalue->value->time, 8));
                                $latestClaim = $subclaim;
                            }
                        }
                    }
                }

                if($latestClaim != null && $latestClaim->mainsnak->snaktype !== 'novalue' && property_exists($latestClaim->mainsnak, 'datatype') && $latestClaim->mainsnak->datatype == 'wikibase-item') {
                    $wikidataId = 'Q'.$latestClaim->mainsnak->datavalue->value->{'numeric-id'};
                    $this->cacheWikiDataData($wikidataId);
                    $this->checkForImageDataAndCacheIt($wikidataId);
                }

                $this->cachePropertyData($latestClaim->mainsnak->property);
            }
        }

        $this->output->writeln('');
    }

    private function retrieveClaims($id){
        $this->output->write('. '.$id.' . ');
        $this->output->writeln('retrieve ClaimData with id: '.$id);

        $result = $this->getRAWDataFromUrl('https://www.wikidata.org/w/api.php?action=wbgetclaims&format=json&entity='.$id);
        $claims = json_decode($result);

        return $claims;
    }

    private function cachePropertyData($id){

        if(!array_key_exists($id, $this->claims)){
            $result = $this->getRAWDataFromUrl('https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.$id);
            $blub = json_decode($result);
            if(!property_exists($blub, 'entities')) {
                var_dump($blub);
                return;
            }
            $propertyData = $blub->entities->$id;

            $this->claims[$id] = $propertyData;

        }
    }

    private function cacheWikiDataData($id){

        if(!array_key_exists($id, $this->wikibaseItems)){
            $result = $this->getRAWDataFromUrl('https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.$id);
            $blub = json_decode($result);
            $blub = $blub->entities->$id;

            $this->wikibaseItems[$id] = $blub;

        }
    }

    private function checkForImageDataAndCacheIt($id){
        $wikidataItem = $this->wikibaseItems[$id];

        if($wikidataItem === null){
            $this->cacheWikiDataData($id);
            $wikidataItem = $this->wikibaseItems[$id];
        }

        if(
            property_exists($wikidataItem, 'claims') &&
            property_exists($wikidataItem->claims, 'P18') &&
            count($wikidataItem->claims->P18) === 1 &&
            $wikidataItem->claims->P18[0]->mainsnak->datatype === 'commonsMedia'
        ) {

            $result = $this->getRAWDataFromUrl(
                'http://tools.wmflabs.org/magnus-toolserver/commonsapi.php?image=' .
                str_replace(' ', '_', $wikidataItem->claims->P18[0]->mainsnak->datavalue->value)
            );

            try{
                $xmlString = simplexml_load_string($result);
                if(!$xmlString ||
                    !property_exists($xmlString, 'file') ||
                    !property_exists($xmlString->file, 'urls') ||
                    !property_exists($xmlString->file->urls, 'file')){
                    return;
                }
                $imgUrl = $xmlString->file->urls->file;
                $imgFileBlob = $this->getRAWDataFromUrl($imgUrl);

                $this->wikibaseFlagImages[$id] = $imgFileBlob;
            } catch (\Exception $e) {
            }
        }
    }


    private $testdata = '{    "Q33": {
        "pageid": 147,
        "ns": 0,
        "title": "Q33",
        "lastrevid": 188530300,
        "modified": "2015-01-17T03:10:37Z",
        "id": "Q33",
        "type": "item",
        "aliases": {
            "en": [
                {
                    "language": "en",
                    "value": "Republic of Finland"
                },
                {
                    "language": "en",
                    "value": "Finlande"
                },
                {
                    "language": "en",
                    "value": "Finlandia"
                },
                {
                    "language": "en",
                    "value": "Finnland"
                },
                {
                    "language": "en",
                    "value": "Finnia"
                },
                {
                    "language": "en",
                    "value": "Soome"
                },
                {
                    "language": "en",
                    "value": "Land of Thousand Lakes"
                }
            ],
            "nl": [
                {
                    "language": "nl",
                    "value": "Republiek Finland"
                }
            ],
            "sgs": [
                {
                    "language": "sgs",
                    "value": "Soum\u0117j\u0117s Respobl\u0117ka"
                },
                {
                    "language": "sgs",
                    "value": "Suom\u0117j\u0117"
                }
            ],
            "nan": [
                {
                    "language": "nan",
                    "value": "Finland"
                },
                {
                    "language": "nan",
                    "value": "Hun-l\u00e2n"
                }
            ],
            "roa-tara": [
                {
                    "language": "roa-tara",
                    "value": "Finlandia"
                }
            ],
            "yue": [
                {
                    "language": "yue",
                    "value": "Suomen tasavalta"
                },
                {
                    "language": "yue",
                    "value": "Finland"
                },
                {
                    "language": "yue",
                    "value": "Republic of Finland"
                },
                {
                    "language": "yue",
                    "value": "Suomi"
                },
                {
                    "language": "yue",
                    "value": "\u82ac\u862d\u5171\u548c\u570b"
                },
                {
                    "language": "yue",
                    "value": "Republiken Finland"
                }
            ],
            "lzh": [
                {
                    "language": "lzh",
                    "value": "\u82ac\u862d\u5171\u548c\u570b"
                }
            ],
            "nds-nl": [
                {
                    "language": "nds-nl",
                    "value": "Finland"
                }
            ],
            "zh-hant": [
                {
                    "language": "zh-hant",
                    "value": "\u82ac\u862d\u5171\u548c\u570b"
                }
            ],
            "tt": [
                {
                    "language": "tt",
                    "value": "Finlyandiya"
                },
                {
                    "language": "tt",
                    "value": "Finl\u00e2ndi\u00e4"
                }
            ],
            "zh-hans": [
                {
                    "language": "zh-hans",
                    "value": "\u82ac\u5170\u5171\u548c\u56fd"
                }
            ],
            "zh-cn": [
                {
                    "language": "zh-cn",
                    "value": "\u82ac\u5170\u5171\u548c\u56fd"
                }
            ],
            "zh-sg": [
                {
                    "language": "zh-sg",
                    "value": "\u82ac\u5170\u5171\u548c\u56fd"
                }
            ],
            "zh-my": [
                {
                    "language": "zh-my",
                    "value": "\u82ac\u5170\u5171\u548c\u56fd"
                }
            ],
            "zh": [
                {
                    "language": "zh",
                    "value": "\u82ac\u5170\u5171\u548c\u56fd"
                }
            ],
            "zh-hk": [
                {
                    "language": "zh-hk",
                    "value": "\u82ac\u862d\u5171\u548c\u570b"
                }
            ],
            "zh-tw": [
                {
                    "language": "zh-tw",
                    "value": "\u82ac\u862d\u5171\u548c\u570b"
                }
            ],
            "zh-mo": [
                {
                    "language": "zh-mo",
                    "value": "\u82ac\u862d\u5171\u548c\u570b"
                }
            ],
            "ca": [
                {
                    "language": "ca",
                    "value": "Rep\u00fablica de Finl\u00e0ndia"
                },
                {
                    "language": "ca",
                    "value": "Finlandia"
                }
            ],
            "fi": [
                {
                    "language": "fi",
                    "value": "Suomen tasavalta"
                }
            ],
            "sv": [
                {
                    "language": "sv",
                    "value": "Republiken Finland"
                }
            ],
            "nb": [
                {
                    "language": "nb",
                    "value": "Republikken Finland"
                }
            ],
            "th": [
                {
                    "language": "th",
                    "value": "\u0e1f\u0e34\u0e19\u0e41\u0e25\u0e19\u0e14\u0e4c"
                },
                {
                    "language": "th",
                    "value": "\u0e0b\u0e39\u0e42\u0e2d\u0e21\u0e34"
                },
                {
                    "language": "th",
                    "value": "Finland"
                },
                {
                    "language": "th",
                    "value": "\u0e2a\u0e32\u0e18\u0e32\u0e23\u0e13\u0e23\u0e31\u0e10\u0e1f\u0e34\u0e19\u0e41\u0e25\u0e19\u0e14\u0e4c"
                }
            ],
            "pt-br": [
                {
                    "language": "pt-br",
                    "value": "Rep\u00fablica da Finl\u00e2ndia"
                }
            ],
            "ta": [
                {
                    "language": "ta",
                    "value": "\u0b83\u0baa\u0bbf\u0ba9\u0bcd\u0bb2\u0bbe\u0ba3\u0bcd\u0b9f\u0bcd"
                },
                {
                    "language": "ta",
                    "value": "\u0b9a\u0bc1\u0bb5\u0bcb\u0bae\u0bbf"
                },
                {
                    "language": "ta",
                    "value": "\u0b83\u0baa\u0bbf\u0ba9\u0bcd\u0bb2\u0bbe\u0ba8\u0bcd\u0ba4\u0bc1"
                }
            ],
            "ru": [
                {
                    "language": "ru",
                    "value": "\u0421\u0443\u043e\u043c\u0438"
                },
                {
                    "language": "ru",
                    "value": "Suomi"
                }
            ],
            "pt": [
                {
                    "language": "pt",
                    "value": "Rep\u00fablica da Finl\u00e2ndia"
                }
            ],
            "es": [
                {
                    "language": "es",
                    "value": "Rep\u00fablica de Finlandia"
                }
            ],
            "el": [
                {
                    "language": "el",
                    "value": "\u0394\u03b7\u03bc\u03bf\u03ba\u03c1\u03b1\u03c4\u03af\u03b1 \u03c4\u03b7\u03c2 \u03a6\u03b9\u03bd\u03bb\u03b1\u03bd\u03b4\u03af\u03b1\u03c2"
                }
            ],
            "da": [
                {
                    "language": "da",
                    "value": "Republikken Finland"
                }
            ],
            "no": [
                {
                    "language": "no",
                    "value": "Republikken Finland"
                }
            ]
        },
        "labels": {
            "en": {
                "language": "en",
                "value": "Finland"
            },
            "nb": {
                "language": "nb",
                "value": "Finland"
            },
            "fi": {
                "language": "fi",
                "value": "Suomi"
            },
            "fr": {
                "language": "fr",
                "value": "Finlande"
            },
            "it": {
                "language": "it",
                "value": "Finlandia"
            },
            "nl": {
                "language": "nl",
                "value": "Finland"
            },
            "pl": {
                "language": "pl",
                "value": "Finlandia"
            },
            "eo": {
                "language": "eo",
                "value": "Finnlando"
            },
            "de": {
                "language": "de",
                "value": "Finnland"
            },
            "ru": {
                "language": "ru",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "es": {
                "language": "es",
                "value": "Finlandia"
            },
            "be-tarask": {
                "language": "be-tarask",
                "value": "\u0424\u0456\u043d\u043b\u044f\u043d\u0434\u044b\u044f"
            },
            "sgs": {
                "language": "sgs",
                "value": "Soum\u0117j\u0117"
            },
            "rup": {
                "language": "rup",
                "value": "Finlanda"
            },
            "nan": {
                "language": "nan",
                "value": "Suomi"
            },
            "vro": {
                "language": "vro",
                "value": "Soom\u00f5"
            },
            "roa-tara": {
                "language": "roa-tara",
                "value": "Finlandie"
            },
            "yue": {
                "language": "yue",
                "value": "\u82ac\u862d"
            },
            "lzh": {
                "language": "lzh",
                "value": "\u82ac\u862d"
            },
            "nds-nl": {
                "language": "nds-nl",
                "value": "Finlaand"
            },
            "en-gb": {
                "language": "en-gb",
                "value": "Finland"
            },
            "ja": {
                "language": "ja",
                "value": "\u30d5\u30a3\u30f3\u30e9\u30f3\u30c9"
            },
            "zh-hant": {
                "language": "zh-hant",
                "value": "\u82ac\u862d"
            },
            "en-ca": {
                "language": "en-ca",
                "value": "Finland"
            },
            "sv": {
                "language": "sv",
                "value": "Finland"
            },
            "nn": {
                "language": "nn",
                "value": "Finland"
            },
            "da": {
                "language": "da",
                "value": "Finland"
            },
            "zh": {
                "language": "zh",
                "value": "\u82ac\u5170"
            },
            "zh-cn": {
                "language": "zh-cn",
                "value": "\u82ac\u5170"
            },
            "zh-hans": {
                "language": "zh-hans",
                "value": "\u82ac\u5170"
            },
            "mk": {
                "language": "mk",
                "value": "\u0424\u0438\u043d\u0441\u043a\u0430"
            },
            "ab": {
                "language": "ab",
                "value": "\u0421\u0443\u043e\u043c\u0438"
            },
            "ace": {
                "language": "ace",
                "value": "Finlandia"
            },
            "af": {
                "language": "af",
                "value": "Finland"
            },
            "am": {
                "language": "am",
                "value": "\u134a\u1295\u120b\u1295\u12f5"
            },
            "an": {
                "language": "an",
                "value": "Finlandia"
            },
            "ang": {
                "language": "ang",
                "value": "Finnland"
            },
            "bn": {
                "language": "bn",
                "value": "\u09ab\u09bf\u09a8\u09b2\u09cd\u09af\u09be\u09a8\u09cd\u09a1"
            },
            "ar": {
                "language": "ar",
                "value": "\u0641\u0646\u0644\u0646\u062f\u0627"
            },
            "arc": {
                "language": "arc",
                "value": "\u0726\u071d\u0722\u0720\u0722\u0715"
            },
            "arz": {
                "language": "arz",
                "value": "\u0641\u064a\u0646\u0644\u0627\u0646\u062f\u0627"
            },
            "ast": {
                "language": "ast",
                "value": "Finlandia"
            },
            "ay": {
                "language": "ay",
                "value": "Phini suyu"
            },
            "az": {
                "language": "az",
                "value": "Finlandiya"
            },
            "ba": {
                "language": "ba",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "bar": {
                "language": "bar",
                "value": "Finnland"
            },
            "bcl": {
                "language": "bcl",
                "value": "Finlandya"
            },
            "be": {
                "language": "be",
                "value": "\u0424\u0456\u043d\u043b\u044f\u043d\u0434\u044b\u044f"
            },
            "bg": {
                "language": "bg",
                "value": "\u0424\u0438\u043d\u043b\u0430\u043d\u0434\u0438\u044f"
            },
            "bi": {
                "language": "bi",
                "value": "Finland"
            },
            "bm": {
                "language": "bm",
                "value": "Finland"
            },
            "bo": {
                "language": "bo",
                "value": "\u0f67\u0fa5\u0f72\u0f53\u0f0b\u0f63\u0f53\u0f0d"
            },
            "bpy": {
                "language": "bpy",
                "value": "\u09ab\u09bf\u09a8\u09b2\u09cd\u09af\u09be\u09a8\u09cd\u09a1"
            },
            "br": {
                "language": "br",
                "value": "Finland"
            },
            "bs": {
                "language": "bs",
                "value": "Finska"
            },
            "ca": {
                "language": "ca",
                "value": "Finl\u00e0ndia"
            },
            "cdo": {
                "language": "cdo",
                "value": "H\u016dng-l\u00e0ng"
            },
            "ce": {
                "language": "ce",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438"
            },
            "ceb": {
                "language": "ceb",
                "value": "Finlandia"
            },
            "chr": {
                "language": "chr",
                "value": "\u13eb\u13c2\u13b3\u13c2"
            },
            "ckb": {
                "language": "ckb",
                "value": "\u0641\u06cc\u0646\u0644\u0627\u0646\u062f"
            },
            "co": {
                "language": "co",
                "value": "Finlandia"
            },
            "cs": {
                "language": "cs",
                "value": "Finsko"
            },
            "csb": {
                "language": "csb",
                "value": "Fi\u0144sk\u00f4"
            },
            "cu": {
                "language": "cu",
                "value": "\u0421\u043e\u0443\u043c\u044c"
            },
            "cv": {
                "language": "cv",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438"
            },
            "cy": {
                "language": "cy",
                "value": "Y Ffindir"
            },
            "diq": {
                "language": "diq",
                "value": "Finlanda"
            },
            "dsb": {
                "language": "dsb",
                "value": "Finska"
            },
            "dv": {
                "language": "dv",
                "value": "\u078a\u07a8\u0782\u07b0\u078d\u07ad\u0782\u07b0\u0791\u07aa"
            },
            "dz": {
                "language": "dz",
                "value": "\u0f55\u0f72\u0f53\u0f0b\u0f63\u0f7a\u0f53\u0f4c\u0f0b"
            },
            "ee": {
                "language": "ee",
                "value": "Finland"
            },
            "el": {
                "language": "el",
                "value": "\u03a6\u03b9\u03bd\u03bb\u03b1\u03bd\u03b4\u03af\u03b1"
            },
            "et": {
                "language": "et",
                "value": "Soome"
            },
            "eu": {
                "language": "eu",
                "value": "Finlandia"
            },
            "ext": {
                "language": "ext",
                "value": "Finl\u00e1ndia"
            },
            "fa": {
                "language": "fa",
                "value": "\u0641\u0646\u0644\u0627\u0646\u062f"
            },
            "fo": {
                "language": "fo",
                "value": "Finnland"
            },
            "frp": {
                "language": "frp",
                "value": "Finlande"
            },
            "frr": {
                "language": "frr",
                "value": "Finl\u00f6nj"
            },
            "fur": {
                "language": "fur",
                "value": "Finlande"
            },
            "fy": {
                "language": "fy",
                "value": "Finl\u00e2n"
            },
            "ga": {
                "language": "ga",
                "value": "An Fhionlainn"
            },
            "gag": {
                "language": "gag",
                "value": "Finlandiya"
            },
            "gan": {
                "language": "gan",
                "value": "\u82ac\u862d"
            },
            "gd": {
                "language": "gd",
                "value": "Su\u00f2maidh"
            },
            "gl": {
                "language": "gl",
                "value": "Finlandia"
            },
            "gn": {
                "language": "gn",
                "value": "H\u0129landia"
            },
            "got": {
                "language": "got",
                "value": "\ud800\udf46\ud800\udf39\ud800\udf3d\ud800\udf3d\ud800\udf30\ud800\udf3b\ud800\udf30\ud800\udf3d\ud800\udf33"
            },
            "gu": {
                "language": "gu",
                "value": "\u0aab\u0ac0\u0aa8\u0ab2\u0ac7\u0a82\u0aa1"
            },
            "gv": {
                "language": "gv",
                "value": "Finnlynn"
            },
            "hak": {
                "language": "hak",
                "value": "F\u00fbn-l\u00e0n"
            },
            "haw": {
                "language": "haw",
                "value": "Pinilana"
            },
            "he": {
                "language": "he",
                "value": "\u05e4\u05d9\u05e0\u05dc\u05e0\u05d3"
            },
            "hi": {
                "language": "hi",
                "value": "\u092b\u093c\u093f\u0928\u0932\u0948\u0923\u094d\u0921"
            },
            "hif": {
                "language": "hif",
                "value": "Finland"
            },
            "hr": {
                "language": "hr",
                "value": "Finska"
            },
            "hsb": {
                "language": "hsb",
                "value": "Finska"
            },
            "ht": {
                "language": "ht",
                "value": "Fenlann"
            },
            "hu": {
                "language": "hu",
                "value": "Finnorsz\u00e1g"
            },
            "hy": {
                "language": "hy",
                "value": "\u0556\u056b\u0576\u056c\u0561\u0576\u0564\u056b\u0561"
            },
            "ia": {
                "language": "ia",
                "value": "Finlandia"
            },
            "id": {
                "language": "id",
                "value": "Finlandia"
            },
            "ie": {
                "language": "ie",
                "value": "Finland"
            },
            "ig": {
                "language": "ig",
                "value": "Finland"
            },
            "ilo": {
                "language": "ilo",
                "value": "Pinlandia"
            },
            "io": {
                "language": "io",
                "value": "Finlando"
            },
            "is": {
                "language": "is",
                "value": "Finnland"
            },
            "jbo": {
                "language": "jbo",
                "value": "gugdrsu,omi"
            },
            "jv": {
                "language": "jv",
                "value": "Finlandia"
            },
            "ka": {
                "language": "ka",
                "value": "\u10e4\u10d8\u10dc\u10d4\u10d7\u10d8"
            },
            "kaa": {
                "language": "kaa",
                "value": "Finlyandiya"
            },
            "kbd": {
                "language": "kbd",
                "value": "\u0424\u0438\u043d\u043b\u044d\u043d\u0434"
            },
            "kg": {
                "language": "kg",
                "value": "Finlandi"
            },
            "ki": {
                "language": "ki",
                "value": "Finland"
            },
            "kk": {
                "language": "kk",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "kl": {
                "language": "kl",
                "value": "Finlandi"
            },
            "kn": {
                "language": "kn",
                "value": "\u0cab\u0cbf\u0ca8\u0ccd \u0cb2\u0ccd\u0caf\u0cbe\u0c82\u0ca1\u0ccd"
            },
            "ko": {
                "language": "ko",
                "value": "\ud540\ub780\ub4dc"
            },
            "koi": {
                "language": "koi",
                "value": "\u0421\u0443\u043e\u043c\u0438"
            },
            "krc": {
                "language": "krc",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "ku": {
                "language": "ku",
                "value": "F\u00eenland"
            },
            "kv": {
                "language": "kv",
                "value": "\u0421\u0443\u043e\u043c\u0438 \u041c\u0443"
            },
            "kw": {
                "language": "kw",
                "value": "Pow Finn"
            },
            "ky": {
                "language": "ky",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "la": {
                "language": "la",
                "value": "Finnia"
            },
            "lad": {
                "language": "lad",
                "value": "Finlandia"
            },
            "lb": {
                "language": "lb",
                "value": "Finnland"
            },
            "lez": {
                "language": "lez",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "lg": {
                "language": "lg",
                "value": "Finilandi"
            },
            "li": {
                "language": "li",
                "value": "Finland"
            },
            "lij": {
                "language": "lij",
                "value": "Finlandia"
            },
            "lmo": {
                "language": "lmo",
                "value": "Finlandia"
            },
            "ln": {
                "language": "ln",
                "value": "Finilanda"
            },
            "lt": {
                "language": "lt",
                "value": "Suomija"
            },
            "ltg": {
                "language": "ltg",
                "value": "Suomeja"
            },
            "lv": {
                "language": "lv",
                "value": "Somija"
            },
            "mdf": {
                "language": "mdf",
                "value": "\u0421\u0443\u043e\u043c\u0438 \u043c\u0430\u0441\u0442\u043e\u0440"
            },
            "mg": {
                "language": "mg",
                "value": "Finlandy"
            },
            "mhr": {
                "language": "mhr",
                "value": "\u0421\u0443\u043e\u043c\u0438"
            },
            "mi": {
                "language": "mi",
                "value": "Hinerangi"
            },
            "ml": {
                "language": "ml",
                "value": "\u0d2b\u0d3f\u0d7b\u0d32\u0d3e\u0d28\u0d4d\u0d31\u0d4d"
            },
            "mn": {
                "language": "mn",
                "value": "\u0424\u0438\u043d\u043b\u0430\u043d\u0434"
            },
            "mr": {
                "language": "mr",
                "value": "\u092b\u093f\u0928\u0932\u0902\u0921"
            },
            "mrj": {
                "language": "mrj",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438"
            },
            "ms": {
                "language": "ms",
                "value": "Finland"
            },
            "mt": {
                "language": "mt",
                "value": "Finlandja"
            },
            "my": {
                "language": "my",
                "value": "\u1016\u1004\u103a\u101c\u1014\u103a\u1014\u102d\u102f\u1004\u103a\u1004\u1036"
            },
            "myv": {
                "language": "myv",
                "value": "\u0421\u0443\u043e\u043c\u0438 \u041c\u0430\u0441\u0442\u043e\u0440"
            },
            "na": {
                "language": "na",
                "value": "Pinrand"
            },
            "nah": {
                "language": "nah",
                "value": "Fintl\u0101lpan"
            },
            "nds": {
                "language": "nds",
                "value": "Finnland"
            },
            "ne": {
                "language": "ne",
                "value": "\u092b\u093f\u0928\u0932\u094d\u092f\u093e\u0923\u094d\u0921"
            },
            "new": {
                "language": "new",
                "value": "\u092b\u093f\u0928\u0932\u094d\u092f\u093e\u0928\u094d\u0921"
            },
            "nov": {
                "language": "nov",
                "value": "Finlande"
            },
            "nrm": {
                "language": "nrm",
                "value": "F\u00eenlande"
            },
            "nv": {
                "language": "nv",
                "value": "Nahodits\u02bc\u01eb\u02bc\u0142\u00e1n\u00ed"
            },
            "oc": {
                "language": "oc",
                "value": "Finl\u00e0ndia"
            },
            "or": {
                "language": "or",
                "value": "\u0b2b\u0b3f\u0b28\u0b32\u0b4d\u0b5f\u0b3e\u0b23\u0b4d\u0b21"
            },
            "os": {
                "language": "os",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438"
            },
            "pam": {
                "language": "pam",
                "value": "Pinlandya"
            },
            "pap": {
                "language": "pap",
                "value": "Finlandia"
            },
            "pcd": {
                "language": "pcd",
                "value": "Finlinde"
            },
            "pih": {
                "language": "pih",
                "value": "Finland"
            },
            "pms": {
                "language": "pms",
                "value": "Finlandia"
            },
            "pnb": {
                "language": "pnb",
                "value": "\u0641\u0646\u0644\u06cc\u0646\u0688"
            },
            "pnt": {
                "language": "pnt",
                "value": "\u03a6\u03b9\u03bd\u03bb\u03b1\u03bd\u03b4\u03af\u03b1"
            },
            "ps": {
                "language": "ps",
                "value": "\u0641\u06d0\u0646\u0644\u0627\u0646\u0689"
            },
            "pt": {
                "language": "pt",
                "value": "Finl\u00e2ndia"
            },
            "qu": {
                "language": "qu",
                "value": "Phinsuyu"
            },
            "rm": {
                "language": "rm",
                "value": "Finlanda"
            },
            "rmy": {
                "language": "rmy",
                "value": "Finland"
            },
            "ro": {
                "language": "ro",
                "value": "Finlanda"
            },
            "rue": {
                "language": "rue",
                "value": "\u0424\u0456\u043d\u044c\u0441\u043a\u043e"
            },
            "rw": {
                "language": "rw",
                "value": "Finilande"
            },
            "sa": {
                "language": "sa",
                "value": "\u092b\u093f\u0928\u094d\u0932\u0948\u0902\u0921"
            },
            "sah": {
                "language": "sah",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "sc": {
                "language": "sc",
                "value": "Finlandia"
            },
            "scn": {
                "language": "scn",
                "value": "Finlandia"
            },
            "sco": {
                "language": "sco",
                "value": "Finland"
            },
            "se": {
                "language": "se",
                "value": "Suopma"
            },
            "sh": {
                "language": "sh",
                "value": "Finska"
            },
            "si": {
                "language": "si",
                "value": "\u0dc6\u0dd2\u0db1\u0dca\u0dbd\u0db1\u0dca\u0dad\u0dba"
            },
            "sk": {
                "language": "sk",
                "value": "F\u00ednsko"
            },
            "sl": {
                "language": "sl",
                "value": "Finska"
            },
            "sm": {
                "language": "sm",
                "value": "Finalagi"
            },
            "so": {
                "language": "so",
                "value": "Finland"
            },
            "sq": {
                "language": "sq",
                "value": "Finlanda"
            },
            "sr": {
                "language": "sr",
                "value": "\u0424\u0438\u043d\u0441\u043a\u0430"
            },
            "srn": {
                "language": "srn",
                "value": "Finland"
            },
            "ss": {
                "language": "ss",
                "value": "IFini"
            },
            "st": {
                "language": "st",
                "value": "Finland"
            },
            "stq": {
                "language": "stq",
                "value": "Finlound"
            },
            "su": {
                "language": "su",
                "value": "Finlandia"
            },
            "sw": {
                "language": "sw",
                "value": "Ufini"
            },
            "szl": {
                "language": "szl",
                "value": "Finlandyjo"
            },
            "ta": {
                "language": "ta",
                "value": "\u0baa\u0bbf\u0ba9\u0bcd\u0bb2\u0bbe\u0ba8\u0bcd\u0ba4\u0bc1"
            },
            "te": {
                "language": "te",
                "value": "\u0c2b\u0c3f\u0c28\u0c4d \u0c32\u0c3e\u0c02\u0c21\u0c4d"
            },
            "tet": {
                "language": "tet",
                "value": "Finl\u00e1ndia"
            },
            "tg": {
                "language": "tg",
                "value": "\u0424\u0438\u043d\u043b\u0430\u043d\u0434"
            },
            "th": {
                "language": "th",
                "value": "\u0e1b\u0e23\u0e30\u0e40\u0e17\u0e28\u0e1f\u0e34\u0e19\u0e41\u0e25\u0e19\u0e14\u0e4c"
            },
            "tk": {
                "language": "tk",
                "value": "Finl\u00fdandi\u00fda"
            },
            "tl": {
                "language": "tl",
                "value": "Pinlandiya"
            },
            "tpi": {
                "language": "tpi",
                "value": "Pinlan"
            },
            "tr": {
                "language": "tr",
                "value": "Finlandiya"
            },
            "tt": {
                "language": "tt",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "udm": {
                "language": "udm",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "ug": {
                "language": "ug",
                "value": "\u0641\u0649\u0646\u0644\u0627\u0646\u062f\u0649\u064a\u06d5"
            },
            "uk": {
                "language": "uk",
                "value": "\u0424\u0456\u043d\u043b\u044f\u043d\u0434\u0456\u044f"
            },
            "ur": {
                "language": "ur",
                "value": "\u0641\u0646 \u0644\u06cc\u0646\u0688"
            },
            "uz": {
                "language": "uz",
                "value": "Finlandiya"
            },
            "vec": {
                "language": "vec",
                "value": "Finlandia"
            },
            "vep": {
                "language": "vep",
                "value": "Suomenma"
            },
            "vi": {
                "language": "vi",
                "value": "Ph\u1ea7n Lan"
            },
            "vls": {
                "language": "vls",
                "value": "Finland"
            },
            "vo": {
                "language": "vo",
                "value": "Suomiy\u00e4n"
            },
            "wa": {
                "language": "wa",
                "value": "Finlande"
            },
            "war": {
                "language": "war",
                "value": "Finlandya"
            },
            "wo": {
                "language": "wo",
                "value": "Finlaand"
            },
            "wuu": {
                "language": "wuu",
                "value": "\u82ac\u5170"
            },
            "xal": {
                "language": "xal",
                "value": "\u0421\u0443\u04bb\u043e\u043c\u0443\u0434\u0438\u043d \u041e\u0440\u043d"
            },
            "xmf": {
                "language": "xmf",
                "value": "\u10e4\u10d8\u10dc\u10d4\u10d7\u10d8"
            },
            "yi": {
                "language": "yi",
                "value": "\u05e4\u05d9\u05e0\u05dc\u05d0\u05e0\u05d3"
            },
            "yo": {
                "language": "yo",
                "value": "F\u00ednl\u00e1nd\u00ec"
            },
            "za": {
                "language": "za",
                "value": "Finlan"
            },
            "zea": {
                "language": "zea",
                "value": "Finland"
            },
            "zu": {
                "language": "zu",
                "value": "IFinlandi"
            },
            "de-ch": {
                "language": "de-ch",
                "value": "Finnland"
            },
            "pt-br": {
                "language": "pt-br",
                "value": "Finl\u00e2ndia"
            },
            "zh-sg": {
                "language": "zh-sg",
                "value": "\u82ac\u5170"
            },
            "zh-my": {
                "language": "zh-my",
                "value": "\u82ac\u5170"
            },
            "zh-hk": {
                "language": "zh-hk",
                "value": "\u82ac\u862d"
            },
            "zh-tw": {
                "language": "zh-tw",
                "value": "\u82ac\u862d"
            },
            "zh-mo": {
                "language": "zh-mo",
                "value": "\u82ac\u862d"
            },
            "bxr": {
                "language": "bxr",
                "value": "\u0424\u0438\u043d\u043b\u0430\u043d\u0434"
            },
            "sma": {
                "language": "sma",
                "value": "S\u00e5evmie"
            },
            "liv": {
                "language": "liv",
                "value": "S\u016bom\u00f5m\u014d"
            },
            "gsw": {
                "language": "gsw",
                "value": "Finnland"
            },
            "tokipona": {
                "language": "tokipona",
                "value": "ma Sumi"
            },
            "sr-ec": {
                "language": "sr-ec",
                "value": "\u0424\u0438\u043d\u0441\u043a\u0430"
            },
            "sr-el": {
                "language": "sr-el",
                "value": "Finska"
            },
            "crh-latn": {
                "language": "crh-latn",
                "value": "Finlandiya"
            },
            "pa": {
                "language": "pa",
                "value": "\u0a2b\u0a3c\u0a3f\u0a28\u0a32\u0a48\u0a02\u0a21"
            },
            "lo": {
                "language": "lo",
                "value": "\u0e9b\u0eb0\u0ec0\u0e97\u0e94\u0ec1\u0e9f\u0e87\u0ea5\u0eb1\u0e87"
            },
            "nap": {
                "language": "nap",
                "value": "Finlandia"
            },
            "ha": {
                "language": "ha",
                "value": "Finland"
            },
            "sn": {
                "language": "sn",
                "value": "Finland"
            },
            "tyv": {
                "language": "tyv",
                "value": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f"
            },
            "tw": {
                "language": "tw",
                "value": "Finland"
            },
            "om": {
                "language": "om",
                "value": "Fiinlaandi"
            },
            "bh": {
                "language": "bh",
                "value": "\u092b\u093f\u0928\u0932\u0948\u0902\u0921"
            },
            "mzn": {
                "language": "mzn",
                "value": "\u0641\u0644\u0627\u0646\u062f"
            }
        },
        "descriptions": {
            "en": {
                "language": "en",
                "value": "country in Northern Europe"
            },
            "nb": {
                "language": "nb",
                "value": "land i Europa"
            },
            "fi": {
                "language": "fi",
                "value": "valtio Pohjois-Euroopassa"
            },
            "it": {
                "language": "it",
                "value": "Stato dell\'Europa settentrionale, membro dell\'Unione europea"
            },
            "nl": {
                "language": "nl",
                "value": "land in Noord-Europa"
            },
            "de": {
                "language": "de",
                "value": "Staat in Nordeuropa"
            },
            "ru": {
                "language": "ru",
                "value": "\u0433\u043e\u0441\u0443\u0434\u0430\u0440\u0441\u0442\u0432\u043e \u0432 \u0415\u0432\u0440\u043e\u043f\u0435"
            },
            "es": {
                "language": "es",
                "value": "pa\u00eds de Europa"
            },
            "fr": {
                "language": "fr",
                "value": "pays d\'Europe"
            },
            "en-gb": {
                "language": "en-gb",
                "value": "country in Northern Europe"
            },
            "zh-hant": {
                "language": "zh-hant",
                "value": "\u5317\u6b50\u570b\u5bb6"
            },
            "stq": {
                "language": "stq",
                "value": "n Lound in W\u00e4\u00e4st-Europa"
            },
            "et": {
                "language": "et",
                "value": "paikneb P\u00f5hja-Euroopas"
            },
            "zh-hans": {
                "language": "zh-hans",
                "value": "\u5317\u6b27\u56fd\u5bb6"
            },
            "zh-cn": {
                "language": "zh-cn",
                "value": "\u5317\u6b27\u56fd\u5bb6"
            },
            "zh-sg": {
                "language": "zh-sg",
                "value": "\u5317\u6b27\u56fd\u5bb6"
            },
            "zh-my": {
                "language": "zh-my",
                "value": "\u5317\u6b27\u56fd\u5bb6"
            },
            "zh": {
                "language": "zh",
                "value": "\u5317\u6b27\u56fd\u5bb6"
            },
            "zh-hk": {
                "language": "zh-hk",
                "value": "\u5317\u6b50\u570b\u5bb6"
            },
            "zh-tw": {
                "language": "zh-tw",
                "value": "\u5317\u6b50\u570b\u5bb6"
            },
            "zh-mo": {
                "language": "zh-mo",
                "value": "\u5317\u6b50\u570b\u5bb6"
            },
            "ca": {
                "language": "ca",
                "value": "estat del nord-est d\'Europa"
            },
            "sv": {
                "language": "sv",
                "value": "republik i norra Europa"
            },
            "ilo": {
                "language": "ilo",
                "value": "pagilian idiay Amianan nga Europa"
            },
            "la": {
                "language": "la",
                "value": "civitas Europae"
            },
            "pt-br": {
                "language": "pt-br",
                "value": "pa\u00eds no norte da Europa"
            },
            "ta": {
                "language": "ta",
                "value": "\u0bb5\u0b9f\u0b95\u0bbf\u0bb4\u0b95\u0bcd\u0b95\u0bc1 \u0b90\u0bb0\u0bcb\u0baa\u0bcd\u0baa\u0bbf\u0baf \u0b95\u0bc1\u0b9f\u0bbf\u0baf\u0bb0\u0b9a\u0bc1 \u0ba8\u0bbe\u0b9f\u0bc1"
            },
            "pl": {
                "language": "pl",
                "value": "pa\u0144stwo w p\u00f3\u0142nocnej Europie"
            },
            "ja": {
                "language": "ja",
                "value": "\u5317\u30e8\u30fc\u30ed\u30c3\u30d1\u306b\u4f4d\u7f6e\u3059\u308b\u56fd\u5bb6"
            },
            "sr": {
                "language": "sr",
                "value": "\u0434\u0440\u0436\u0430\u0432\u0430 \u0443 \u0441\u0435\u0432\u0435\u0440\u043d\u043e\u0458 \u0415\u0432\u0440\u043e\u043f\u0438"
            },
            "sr-ec": {
                "language": "sr-ec",
                "value": "\u0434\u0440\u0436\u0430\u0432\u0430 \u0443 \u0441\u0435\u0432\u0435\u0440\u043d\u043e\u0458 \u0415\u0432\u0440\u043e\u043f\u0438"
            },
            "sr-el": {
                "language": "sr-el",
                "value": "dr\u017eava u severnoj Evropi"
            },
            "th": {
                "language": "th",
                "value": "\u0e1b\u0e23\u0e30\u0e40\u0e17\u0e28\u0e43\u0e19\u0e22\u0e38\u0e42\u0e23\u0e1b\u0e40\u0e2b\u0e19\u0e37\u0e2d"
            },
            "pt": {
                "language": "pt",
                "value": "pa\u00eds n\u00f3rdico situado na regi\u00e3o da Fino-Escandin\u00e1via, no norte da Europa"
            },
            "uk": {
                "language": "uk",
                "value": "\u0434\u0435\u0440\u0436\u0430\u0432\u0430 \u0443 \u041f\u0456\u0432\u043d\u0456\u0447\u043d\u0456\u0439 \u0404\u0432\u0440\u043e\u043f\u0456"
            },
            "hu": {
                "language": "hu",
                "value": "\u00e1llam \u00c9szak-Eur\u00f3p\u00e1ban"
            },
            "ro": {
                "language": "ro",
                "value": "stat \u00een Europa de Nord"
            },
            "el": {
                "language": "el",
                "value": "\u03c7\u03ce\u03c1\u03b1 \u03c4\u03b7\u03c2 \u03b2\u03cc\u03c1\u03b5\u03b9\u03b1\u03c2 \u0395\u03c5\u03c1\u03ce\u03c0\u03b7\u03c2"
            },
            "da": {
                "language": "da",
                "value": "land i Nordeuropa"
            }
        },
        "claims": {
            "P1464": [
                {
                    "id": "Q33$20ff48dc-49ef-fe8f-5361-adcfeeb2b6d1",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1464",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 8077216
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "d6e3ab4045fb3f3feea77895bc6b27e663fc878a",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 206855
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143"
                            ]
                        }
                    ]
                }
            ],
            "P1332": [
                {
                    "id": "Q33$62f8fbb6-43ee-efd3-6e27-6707b0e8a211",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1332",
                        "datatype": "globe-coordinate",
                        "datavalue": {
                            "value": {
                                "latitude": 70.0825,
                                "longitude": 27.884166666667,
                                "altitude": null,
                                "precision": 0.00027777777777778,
                                "globe": "http:\/\/www.wikidata.org\/entity\/Q2"
                            },
                            "type": "globecoordinate"
                        }
                    },
                    "qualifiers": {
                        "P805": [
                            {
                                "hash": "87b8ed765a0004f81403821eac66d82e5ab8a8a8",
                                "snaktype": "value",
                                "property": "P805",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 520764
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P805"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P138": [
                {
                    "id": "Q33$57cff515-4a3f-e818-1933-4a73295d7c53",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P138",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 170284
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P1036": [
                {
                    "id": "Q33$b65b652b-4a28-d8e0-02e3-fb8c7c954e4f",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1036",
                        "datatype": "string",
                        "datavalue": {
                            "value": "2--4897",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P1151": [
                {
                    "id": "Q33$0522b50f-4e08-e41d-8fe6-4dc55aa5ffe0",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1151",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 8287709
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P30": [
                {
                    "id": "q33$07AD9D8C-A845-493D-82DA-862DAD23BC61",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P30",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 46
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P36": [
                {
                    "id": "q33$D0472690-0F96-4CD5-9275-01DE9246835A",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P36",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 1757
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "4575dca239f00437201356fdf4ff8770c3212764",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001812-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 9,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "a306dab29532dd16096791759b1b273297961a8e",
                            "snaks": {
                                "P248": [
                                    {
                                        "snaktype": "value",
                                        "property": "P248",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 14334357
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ],
                                "P304": [
                                    {
                                        "snaktype": "value",
                                        "property": "P304",
                                        "datatype": "string",
                                        "datavalue": {
                                            "value": "388",
                                            "type": "string"
                                        }
                                    }
                                ],
                                "P1683": [
                                    {
                                        "snaktype": "value",
                                        "property": "P1683",
                                        "datatype": "monolingualtext",
                                        "datavalue": {
                                            "value": {
                                                "text": "Kun Suomen asiain komitea ja kenraalikuvern\u00f6\u00f6ri Steinheil olivat asettuneet tukemaan maan p\u00e4\u00e4kaupunkisuunitelmaa, tehtiin huhtikuussa 1812 p\u00e4\u00e4t\u00f6s Helsingin korottamisesta Suomen p\u00e4\u00e4kaupungiksi.",
                                                "language": "fi"
                                            },
                                            "type": "monolingualtext"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P248",
                                "P304",
                                "P1683"
                            ]
                        }
                    ]
                }
            ],
            "P37": [
                {
                    "id": "q33$A327D21E-49D5-47CC-8C95-0C6561A132BA",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P37",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 1412
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "af7d38f99d8671011a78cecb74865be01117decc",
                            "snaks": {
                                "P248": [
                                    {
                                        "snaktype": "value",
                                        "property": "P248",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 1357568
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ],
                                "P1683": [
                                    {
                                        "snaktype": "value",
                                        "property": "P1683",
                                        "datatype": "monolingualtext",
                                        "datavalue": {
                                            "value": {
                                                "text": "Suomen kansalliskielet ovat suomi ja ruotsi.",
                                                "language": "fi"
                                            },
                                            "type": "monolingualtext"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P248",
                                "P1683"
                            ]
                        }
                    ]
                },
                {
                    "id": "q33$8ADE0D2C-93A7-41A9-937C-AE06C6E53926",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P37",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 9027
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "af7d38f99d8671011a78cecb74865be01117decc",
                            "snaks": {
                                "P248": [
                                    {
                                        "snaktype": "value",
                                        "property": "P248",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 1357568
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ],
                                "P1683": [
                                    {
                                        "snaktype": "value",
                                        "property": "P1683",
                                        "datatype": "monolingualtext",
                                        "datavalue": {
                                            "value": {
                                                "text": "Suomen kansalliskielet ovat suomi ja ruotsi.",
                                                "language": "fi"
                                            },
                                            "type": "monolingualtext"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P248",
                                "P1683"
                            ]
                        }
                    ]
                }
            ],
            "P38": [
                {
                    "id": "q33$81C70A67-3B57-4872-A1E4-AB626824F9F4",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P38",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 4916
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "2cbca451a4606cf27fc5ea43415f8baac575c6ee",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001999-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$b8eeae6c-4fa3-c25d-ea54-44a626417514",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P38",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 203354
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P582": [
                            {
                                "hash": "4bc8df1cde0c80ac89b0edabe05239ab0ae17a39",
                                "snaktype": "value",
                                "property": "P582",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001998-12-31T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P582"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P41": [
                {
                    "id": "q33$08ADCA31-CD81-4518-9485-B93D11795D69",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P41",
                        "datatype": "commonsMedia",
                        "datavalue": {
                            "value": "Flag of Finland.svg",
                            "type": "string"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "1d3fba747e48caa8dceb1323bd765b93ccc51af4",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001918-05-29T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "5853b3cd9ae05784038b4a3fceebac634c59500d",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 175482
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143"
                            ]
                        }
                    ]
                }
            ],
            "P47": [
                {
                    "id": "q33$AAC4E1C7-8510-4A46-9A99-D0D0670C7F74",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P47",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 34
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P805": [
                            {
                                "hash": "68adbab38f6f07ef0716c30052318c2fbb2dec42",
                                "snaktype": "value",
                                "property": "P805",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 2717530
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P805"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$A1DEE73F-5883-4958-9D07-2609DEC5157B",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P47",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 20
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P805": [
                            {
                                "hash": "33b2b9f4146e12749206b1017c2db4fcae8764df",
                                "snaktype": "value",
                                "property": "P805",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 2913944
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P805"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$BD0392B0-9A92-4CDF-A0A4-7C79A2971CBA",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P47",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 159
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P805": [
                            {
                                "hash": "bdd5a64499a037daae316fb9bd83265386bf0be1",
                                "snaktype": "value",
                                "property": "P805",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 495735
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P805"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P78": [
                {
                    "id": "q33$B83298FE-9596-4CD0-85AE-A28384CBED02",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P78",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 37164
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P31": [
                {
                    "id": "q33$CBE1D73C-6F18-45E6-A437-7657B825E87E",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P31",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 3624078
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "d2e36c7c1cf7d1c63e37b931d36ecb87fd12029c",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001917-12-06T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "8e58ae97512e9ea0828b05b1f6989a6dcd109bd5",
                            "snaks": {
                                "P248": [
                                    {
                                        "snaktype": "value",
                                        "property": "P248",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 14334357
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ],
                                "P304": [
                                    {
                                        "snaktype": "value",
                                        "property": "P304",
                                        "datatype": "string",
                                        "datavalue": {
                                            "value": "603",
                                            "type": "string"
                                        }
                                    }
                                ],
                                "P1683": [
                                    {
                                        "snaktype": "value",
                                        "property": "P1683",
                                        "datatype": "monolingualtext",
                                        "datavalue": {
                                            "value": {
                                                "text": "Joulukuun kuudentena p\u00e4iv\u00e4n\u00e4 vuonna 1917 Suomen eduskunta hyv\u00e4ksyi senaatin ilmoituksen siit\u00e4, ett\u00e4 Suomi oli nyt itsen\u00e4inen.",
                                                "language": "fi"
                                            },
                                            "type": "monolingualtext"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P248",
                                "P304",
                                "P1683"
                            ]
                        }
                    ]
                },
                {
                    "id": "q33$1D955803-700D-4B70-997F-2ABB4C084EB2",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P31",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 6256
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$81CCBEAB-A5E7-404A-B7E3-E46B240E179F",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P31",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 185441
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "d7cc394a1193ccf6cf98889d300d548326bf6aaa",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001995-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "Q33$0888ad3b-482b-1629-7deb-a9394955ce7a",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P31",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 160016
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "5146d225647eba5f608481956111b4e040b325c7",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001955-12-14T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "Q33$54d5a285-4fd3-82a3-57ae-9b12b7ab2148",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P31",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 6505795
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "15499ec0066e49b53cbb727602c170382842afb5",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001989-05-05T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "Q33$cdab5cb1-4e80-6b08-7f5b-bbbacc3db6ca",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P31",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 179164
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P85": [
                {
                    "id": "q33$CFD94E6B-2F29-4435-A870-1239E68264D9",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P85",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 162483
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P94": [
                {
                    "id": "q33$99F7B623-EBF8-45D4-961B-361B883B1F13",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P94",
                        "datatype": "commonsMedia",
                        "datavalue": {
                            "value": "Coat of arms of Finland.svg",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P194": [
                {
                    "id": "q33$60839328-2B72-4BE4-9C83-0828D7D274A8",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P194",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 643412
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P150": [
                {
                    "id": "q33$A295C1ED-9264-42A8-B610-7DF2F0633D8B",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5712
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$4051835C-DD2D-4254-82BF-3C5DFCBEB031",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5711
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$0D0022C6-AC79-4151-AD88-0960FAEA9609",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5709
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$E805850E-E827-43C2-B68F-291C415B5E5F",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5708
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$FAD51526-082F-456D-82E8-B2B6208165D6",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5706
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$E366BDE0-F820-4514-B313-5CC9D58C46D7",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5704
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$183BAE5B-69EB-4D3A-BA09-320FC05AD1B2",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5703
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$EFD42AFA-24C0-446C-8174-13678FA02B0C",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5702
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$EFFF06F1-4A9A-4DE9-A1F2-5677907AA2B0",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5701
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$C039B6C0-F9D1-4533-8FA7-B5D4D6A47FA1",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5700
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$A4939101-B172-4D47-B3E5-36B03D7EFB79",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5698
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$6F4B35D3-2738-44A6-8A49-395B47B66F6C",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5697
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$D4F6ABEC-A8BD-4119-ACD8-F89C20DE3598",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5696
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$078FE693-4105-458E-AF23-D1BEBC79C88E",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5695
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$4072BF73-B02D-456B-BA79-72224672348F",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5694
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$E5C068CA-9F6B-4DEB-B3C7-3F929FA1319C",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5693
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$BA17F48B-8C86-4979-9D01-6F39EC96A661",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5692
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$C5413D05-8EF8-40B0-B12A-E99191548D66",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5691
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$CF6D82E3-EDE3-4F38-9586-0A955CD6E18B",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P150",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 5689
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P163": [
                {
                    "id": "q33$D585F780-F02D-46D9-BB5B-184B60865826",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P163",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 47891
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P297": [
                {
                    "id": "q33$8D8466A0-5E3C-4F64-9553-E382CB591D15",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P297",
                        "datatype": "string",
                        "datavalue": {
                            "value": "FI",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P298": [
                {
                    "id": "q33$083E940F-9069-43B9-BD0B-8A8289F7E28A",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P298",
                        "datatype": "string",
                        "datavalue": {
                            "value": "FIN",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P299": [
                {
                    "id": "q33$ABB67FE5-7A37-4732-AE51-71A27B22E013",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P299",
                        "datatype": "string",
                        "datavalue": {
                            "value": "246",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P373": [
                {
                    "id": "q33$B6E45BDF-3D25-4AD7-9D59-57C365F23083",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P373",
                        "datatype": "string",
                        "datavalue": {
                            "value": "Finland",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P6": [
                {
                    "id": "q33$a4750f4e-46df-1932-49fd-5b88901ca354",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P6",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 503143
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "d901bf7e9524093882c8a3fa696fe1ce199af8c0",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000002014-06-24T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P242": [
                {
                    "id": "q33$9238c32f-4f77-e04e-25a9-10ad35b5170a",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P242",
                        "datatype": "commonsMedia",
                        "datavalue": {
                            "value": "EU-Finland.svg",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P35": [
                {
                    "id": "q33$949B4420-6737-4FE4-B80F-2CD91B11F1CF",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P35",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 29207
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "1f514df27e4ff4dc8de0cbe6329b010a5988179e",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000002012-03-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P625": [
                {
                    "id": "q33$88c7575b-4e46-0a6a-a809-d2407246e018",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P625",
                        "datatype": "globe-coordinate",
                        "datavalue": {
                            "value": {
                                "latitude": 64,
                                "longitude": 26,
                                "altitude": null,
                                "precision": 1,
                                "globe": "http:\/\/www.wikidata.org\/entity\/Q2"
                            },
                            "type": "globecoordinate"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P474": [
                {
                    "id": "q33$6c4eae89-4b36-5309-58b0-d6c54a8762a9",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P474",
                        "datatype": "string",
                        "datavalue": {
                            "value": "+358",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P402": [
                {
                    "id": "q33$850fcf27-4965-12ee-db34-d802fbe705ed",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P402",
                        "datatype": "string",
                        "datavalue": {
                            "value": "54224",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P122": [
                {
                    "id": "q33$0a837c29-49eb-b0d3-b44a-2e1ec81d42e0",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P122",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 4198907
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "7eb64cf9621d34c54fd4bd040ed4b61a88c4a1a0",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 328
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143"
                            ]
                        }
                    ]
                }
            ],
            "P610": [
                {
                    "id": "q33$c9f8daba-4e5a-49fb-81ae-b514337c3870",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P610",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 216035
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P463": [
                {
                    "id": "q33$DACABC5B-116F-436A-9A7A-4945741A3E6A",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 458
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "d7cc394a1193ccf6cf98889d300d548326bf6aaa",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001995-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$03C0A872-6459-4154-9B3D-0F4B1D05C8A7",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 1065
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "5146d225647eba5f608481956111b4e040b325c7",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001955-12-14T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$832C0C4E-63D9-49C1-97C1-93CF80745D5A",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 41550
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "521dabd8f26bb1a4e4b02984545e324fc7a93c00",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001969-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 9,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$0E3F858F-04B9-4616-B304-73F184CF2F08",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 8908
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "15499ec0066e49b53cbb727602c170382842afb5",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001989-05-05T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$0698E4A2-0A76-435E-9315-1F77259AEE3B",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 42262
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "521dabd8f26bb1a4e4b02984545e324fc7a93c00",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001969-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 9,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$C9D6A29A-7C1C-43CE-A069-F714220F0451",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 7825
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "d7cc394a1193ccf6cf98889d300d548326bf6aaa",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001995-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$F43685FB-18C1-4055-A301-B40D4112C113",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 146165
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "bc4a9dd568bc32b78c0b17a4d3f05379db80d79e",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001955-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 9,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$6CDDA729-AA76-433F-AC62-0AB53E1DE018",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 674182
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "15d64c64d3be25d36816a20aa93577e5078ab211",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001996-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 9,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "q33$A92F1155-6AAC-4A13-9971-DAEA4F7A3882",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 1998131
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "Q33$f15f3144-47bb-5250-8668-19fee6601702",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 151991
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "d6744cc6b8cdd01d0865745d6adadd2085645505",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000002004-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 9,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "Q33$546a2fcf-4abb-3a18-66fc-6c89f9fac868",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 789769
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "d338225c69010de6cb98d2d04e0ed1d88ef66fa4",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001992-01-01T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 9,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "Q33$ec35657f-449d-30de-3c95-751a1aa35b59",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P463",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 81299
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P580": [
                            {
                                "hash": "7188914937145bebcd0839c786a482d1951fbdbe",
                                "snaktype": "value",
                                "property": "P580",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001973-06-25T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P580"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P910": [
                {
                    "id": "Q33$C1A7B951-CF83-446A-8B2B-A19F02535083",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P910",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 4367709
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P237": [
                {
                    "id": "Q33$3D4F8873-D6D3-4403-B4A5-7C6B83CDD965",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P237",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 171708
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "d6e3ab4045fb3f3feea77895bc6b27e663fc878a",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 206855
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143"
                            ]
                        }
                    ]
                }
            ],
            "P349": [
                {
                    "id": "Q33$FFF61E63-32BC-42F4-BA30-188FC5F784F4",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P349",
                        "datatype": "string",
                        "datavalue": {
                            "value": "00563226",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P948": [
                {
                    "id": "Q33$52BD495A-9DE5-4D38-9475-1D9A30533333",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P948",
                        "datatype": "commonsMedia",
                        "datavalue": {
                            "value": "Finland Wikivoyage Banner.png",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P214": [
                {
                    "id": "Q33$FC8E9C3F-63C1-4B3E-96A6-1D6BF05E8BD0",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P214",
                        "datatype": "string",
                        "datavalue": {
                            "value": "253570658",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "a51d6594fee36c7452eaed2db35a4833613a7078",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 54919
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143"
                            ]
                        }
                    ]
                }
            ],
            "P984": [
                {
                    "id": "Q33$3B426D42-C741-4C99-A61B-F9306E031AEB",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P984",
                        "datatype": "string",
                        "datavalue": {
                            "value": "FIN",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "d6e3ab4045fb3f3feea77895bc6b27e663fc878a",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 206855
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143"
                            ]
                        }
                    ]
                }
            ],
            "P605": [
                {
                    "id": "Q33$f7a8d078-4063-ac1b-dbc1-5324b59e3a0c",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P605",
                        "datatype": "string",
                        "datavalue": {
                            "value": "FI",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P982": [
                {
                    "id": "Q33$62F06C38-6013-4276-9671-4CDA665E8885",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P982",
                        "datatype": "string",
                        "datavalue": {
                            "value": "6a264f94-6ff1-30b1-9a81-41f7bfabd616",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "73057782312f850e2ed69c4c28ad162f57f6d390",
                            "snaks": {
                                "P248": [
                                    {
                                        "snaktype": "value",
                                        "property": "P248",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 14005
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P248"
                            ]
                        }
                    ]
                }
            ],
            "P17": [
                {
                    "id": "Q33$0E5E6754-9E22-42B7-B4C2-29A256B5B2A0",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P17",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 33
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P646": [
                {
                    "id": "Q33$2A9AAFAD-8BE6-4D35-A4C0-B82016127661",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P646",
                        "datatype": "string",
                        "datavalue": {
                            "value": "\/m\/02vzc",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "b923b0d68beb300866b87ead39f61e63ec30d8af",
                            "snaks": {
                                "P248": [
                                    {
                                        "snaktype": "value",
                                        "property": "P248",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 15241312
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ],
                                "P577": [
                                    {
                                        "snaktype": "value",
                                        "property": "P577",
                                        "datatype": "time",
                                        "datavalue": {
                                            "value": {
                                                "time": "+00000002013-10-28T00:00:00Z",
                                                "timezone": 0,
                                                "before": 0,
                                                "after": 0,
                                                "precision": 11,
                                                "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                            },
                                            "type": "time"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P248",
                                "P577"
                            ]
                        }
                    ]
                }
            ],
            "P227": [
                {
                    "id": "Q33$F3A6CF67-BB53-4C75-94AA-B19CD4A19346",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P227",
                        "datatype": "string",
                        "datavalue": {
                            "value": "4017243-0",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "004ec6fbee857649acdbdbad4f97b2c8571df97b",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 48183
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143"
                            ]
                        }
                    ]
                }
            ],
            "P1082": [
                {
                    "id": "Q33$f8aeba35-4947-58c4-19dc-a5a503d1ec3e",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1082",
                        "datatype": "quantity",
                        "datavalue": {
                            "value": {
                                "amount": "+5470437",
                                "unit": "1",
                                "upperBound": "+5470437",
                                "lowerBound": "+5470437"
                            },
                            "type": "quantity"
                        }
                    },
                    "qualifiers": {
                        "P585": [
                            {
                                "hash": "06b84f206dbdef46cd988045244a26e385cdd2b1",
                                "snaktype": "value",
                                "property": "P585",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000002014-10-31T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P585"
                    ],
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "46b4ebdbfcdca84b81f0e574e78e777cf1c38d21",
                            "snaks": {
                                "P854": [
                                    {
                                        "snaktype": "value",
                                        "property": "P854",
                                        "datatype": "url",
                                        "datavalue": {
                                            "value": "http:\/\/www.stat.fi\/til\/vamuu\/2014\/10\/vamuu_2014_10_2014-11-20_tie_001_fi.html",
                                            "type": "string"
                                        }
                                    }
                                ],
                                "P813": [
                                    {
                                        "snaktype": "value",
                                        "property": "P813",
                                        "datatype": "time",
                                        "datavalue": {
                                            "value": {
                                                "time": "+00000002014-12-01T00:00:00Z",
                                                "timezone": 0,
                                                "before": 0,
                                                "after": 0,
                                                "precision": 11,
                                                "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                            },
                                            "type": "time"
                                        }
                                    }
                                ],
                                "P357": [
                                    {
                                        "snaktype": "value",
                                        "property": "P357",
                                        "datatype": "string",
                                        "datavalue": {
                                            "value": "Suomen ennakkov\u00e4kiluku lokakuun lopussa 5 470 437",
                                            "type": "string"
                                        }
                                    }
                                ],
                                "P123": [
                                    {
                                        "snaktype": "value",
                                        "property": "P123",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 798557
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P854",
                                "P813",
                                "P357",
                                "P123"
                            ]
                        }
                    ]
                }
            ],
            "P901": [
                {
                    "id": "Q33$A1F7312B-1C19-4845-97B3-083F5F806B3F",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P901",
                        "datatype": "string",
                        "datavalue": {
                            "value": "FI",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "efc47ca21c9bb45ffc2ed36fde1428e38a40db83",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 328
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ],
                                "P854": [
                                    {
                                        "snaktype": "value",
                                        "property": "P854",
                                        "datatype": "url",
                                        "datavalue": {
                                            "value": "https:\/\/en.wikipedia.org\/w\/index.php?title=List_of_FIPS_country_codes&oldid=588315474",
                                            "type": "string"
                                        }
                                    }
                                ],
                                "P364": [
                                    {
                                        "snaktype": "value",
                                        "property": "P364",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 1860
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ],
                                "P357": [
                                    {
                                        "snaktype": "value",
                                        "property": "P357",
                                        "datatype": "string",
                                        "datavalue": {
                                            "value": "List of FIPS country codes",
                                            "type": "string"
                                        }
                                    }
                                ],
                                "P813": [
                                    {
                                        "snaktype": "value",
                                        "property": "P813",
                                        "datatype": "time",
                                        "datavalue": {
                                            "value": {
                                                "time": "+00000002013-12-30T00:00:00Z",
                                                "timezone": 0,
                                                "before": 0,
                                                "after": 0,
                                                "precision": 11,
                                                "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                            },
                                            "type": "time"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143",
                                "P854",
                                "P364",
                                "P357",
                                "P813"
                            ]
                        }
                    ]
                }
            ],
            "P1245": [
                {
                    "id": "Q33$e0f6466b-4d4b-846c-c953-c1af3a09ea47",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1245",
                        "datatype": "string",
                        "datavalue": {
                            "value": "7956",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P1365": [
                {
                    "id": "Q33$5e365346-4f8d-4833-35ad-c5131e405442",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1365",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 62633
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P585": [
                            {
                                "hash": "6ef6bd40c6b05b3d3f83109be4f69d58e93ea9b4",
                                "snaktype": "value",
                                "property": "P585",
                                "datatype": "time",
                                "datavalue": {
                                    "value": {
                                        "time": "+00000001917-12-06T00:00:00Z",
                                        "timezone": 0,
                                        "before": 0,
                                        "after": 0,
                                        "precision": 11,
                                        "calendarmodel": "http:\/\/www.wikidata.org\/entity\/Q1985727"
                                    },
                                    "type": "time"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P585"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P1334": [
                {
                    "id": "Q33$2eeda392-4daf-8387-6500-f7108128d8f4",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1334",
                        "datatype": "globe-coordinate",
                        "datavalue": {
                            "value": {
                                "latitude": 62.908611111111,
                                "longitude": 31.584166666667,
                                "altitude": null,
                                "precision": 0.00027777777777778,
                                "globe": "http:\/\/www.wikidata.org\/entity\/Q2"
                            },
                            "type": "globecoordinate"
                        }
                    },
                    "qualifiers": {
                        "P805": [
                            {
                                "hash": "25a4866a221e8d13da970af9cffe33e63948f451",
                                "snaktype": "value",
                                "property": "P805",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 890305
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P805"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P1333": [
                {
                    "id": "Q33$ce580bbc-4dd6-3841-ffa1-a6ad08b667cd",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1333",
                        "datatype": "globe-coordinate",
                        "datavalue": {
                            "value": {
                                "latitude": 59.508333333333,
                                "longitude": 20.353333333333,
                                "altitude": null,
                                "precision": 0.00027777777777778,
                                "globe": "http:\/\/www.wikidata.org\/entity\/Q2"
                            },
                            "type": "globecoordinate"
                        }
                    },
                    "qualifiers": {
                        "P805": [
                            {
                                "hash": "79350532db577d3109f89773d26e5633afcb77e1",
                                "snaktype": "value",
                                "property": "P805",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 696156
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P805"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P1335": [
                {
                    "id": "Q33$c171fcb5-46af-f6c9-6240-cb648439e0ff",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1335",
                        "datatype": "globe-coordinate",
                        "datavalue": {
                            "value": {
                                "latitude": 60.303611111111,
                                "longitude": 19.135277777778,
                                "altitude": null,
                                "precision": 0.00027777777777778,
                                "globe": "http:\/\/www.wikidata.org\/entity\/Q2"
                            },
                            "type": "globecoordinate"
                        }
                    },
                    "qualifiers": {
                        "P805": [
                            {
                                "hash": "5c02fbdc59f37419b7a6617dae7c5e9f786cd084",
                                "snaktype": "value",
                                "property": "P805",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 163395
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P805"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P1566": [
                {
                    "id": "Q33$295A97D9-2CB1-441D-8EB1-ABE9CE5A1CA0",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1566",
                        "datatype": "string",
                        "datavalue": {
                            "value": "660013",
                            "type": "string"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P1465": [
                {
                    "id": "Q33$523068d5-4971-25ae-d37e-f2193fbf9981",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P1465",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 7482595
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal",
                    "references": [
                        {
                            "hash": "d6e3ab4045fb3f3feea77895bc6b27e663fc878a",
                            "snaks": {
                                "P143": [
                                    {
                                        "snaktype": "value",
                                        "property": "P143",
                                        "datatype": "wikibase-item",
                                        "datavalue": {
                                            "value": {
                                                "entity-type": "item",
                                                "numeric-id": 206855
                                            },
                                            "type": "wikibase-entityid"
                                        }
                                    }
                                ]
                            },
                            "snaks-order": [
                                "P143"
                            ]
                        }
                    ]
                }
            ],
            "P131": [
                {
                    "id": "Q33$C010C94A-79A4-4F14-BB91-D2CEA551B15E",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P131",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 1969730
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "type": "statement",
                    "rank": "normal"
                }
            ],
            "P421": [
                {
                    "id": "Q33$7C07D0A5-4D14-46EF-9C48-024B3FFA66D1",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P421",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 6723
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P1264": [
                            {
                                "hash": "42a9a9348ef548d085d63b9d6a86a40c8368a474",
                                "snaktype": "value",
                                "property": "P1264",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 1777301
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P1264"
                    ],
                    "type": "statement",
                    "rank": "normal"
                },
                {
                    "id": "Q33$2D3B9C1B-8614-4BCF-B4F6-A59F8018C317",
                    "mainsnak": {
                        "snaktype": "value",
                        "property": "P421",
                        "datatype": "wikibase-item",
                        "datavalue": {
                            "value": {
                                "entity-type": "item",
                                "numeric-id": 6760
                            },
                            "type": "wikibase-entityid"
                        }
                    },
                    "qualifiers": {
                        "P1264": [
                            {
                                "hash": "4891f94b0ecca13c6c515f10974c5bad8aa1c62e",
                                "snaktype": "value",
                                "property": "P1264",
                                "datatype": "wikibase-item",
                                "datavalue": {
                                    "value": {
                                        "entity-type": "item",
                                        "numeric-id": 36669
                                    },
                                    "type": "wikibase-entityid"
                                }
                            }
                        ]
                    },
                    "qualifiers-order": [
                        "P1264"
                    ],
                    "type": "statement",
                    "rank": "normal"
                }
            ]
        },
        "sitelinks": {
            "abwiki": {
                "site": "abwiki",
                "title": "\u0421\u0443\u043e\u043c\u0438",
                "badges": []
            },
            "acewiki": {
                "site": "acewiki",
                "title": "Finlandia",
                "badges": []
            },
            "afwiki": {
                "site": "afwiki",
                "title": "Finland",
                "badges": []
            },
            "alswiki": {
                "site": "alswiki",
                "title": "Finnland",
                "badges": []
            },
            "amwiki": {
                "site": "amwiki",
                "title": "\u134a\u1295\u120b\u1295\u12f5",
                "badges": []
            },
            "angwiki": {
                "site": "angwiki",
                "title": "Finnland",
                "badges": []
            },
            "anwiki": {
                "site": "anwiki",
                "title": "Finlandia",
                "badges": []
            },
            "arcwiki": {
                "site": "arcwiki",
                "title": "\u0726\u071d\u0722\u0720\u0722\u0715",
                "badges": []
            },
            "arwiki": {
                "site": "arwiki",
                "title": "\u0641\u0646\u0644\u0646\u062f\u0627",
                "badges": [
                    "Q17437798"
                ]
            },
            "arzwiki": {
                "site": "arzwiki",
                "title": "\u0641\u064a\u0646\u0644\u0627\u0646\u062f\u0627",
                "badges": []
            },
            "astwiki": {
                "site": "astwiki",
                "title": "Finlandia",
                "badges": []
            },
            "aywiki": {
                "site": "aywiki",
                "title": "Phini suyu",
                "badges": []
            },
            "azwiki": {
                "site": "azwiki",
                "title": "Finlandiya",
                "badges": []
            },
            "barwiki": {
                "site": "barwiki",
                "title": "Finnland",
                "badges": []
            },
            "bat_smgwiki": {
                "site": "bat_smgwiki",
                "title": "Soum\u0117j\u0117",
                "badges": []
            },
            "bawiki": {
                "site": "bawiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "bclwiki": {
                "site": "bclwiki",
                "title": "Finlandya",
                "badges": []
            },
            "be_x_oldwiki": {
                "site": "be_x_oldwiki",
                "title": "\u0424\u0456\u043d\u043b\u044f\u043d\u0434\u044b\u044f",
                "badges": []
            },
            "bewiki": {
                "site": "bewiki",
                "title": "\u0424\u0456\u043d\u043b\u044f\u043d\u0434\u044b\u044f",
                "badges": []
            },
            "bgwiki": {
                "site": "bgwiki",
                "title": "\u0424\u0438\u043d\u043b\u0430\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "bhwiki": {
                "site": "bhwiki",
                "title": "\u092b\u093f\u0928\u0932\u0948\u0902\u0921",
                "badges": []
            },
            "biwiki": {
                "site": "biwiki",
                "title": "Finland",
                "badges": []
            },
            "bmwiki": {
                "site": "bmwiki",
                "title": "Finland",
                "badges": []
            },
            "bnwiki": {
                "site": "bnwiki",
                "title": "\u09ab\u09bf\u09a8\u09b2\u09cd\u09af\u09be\u09a8\u09cd\u09a1",
                "badges": []
            },
            "bowiki": {
                "site": "bowiki",
                "title": "\u0f67\u0fa5\u0f72\u0f53\u0f0b\u0f63\u0f53\u0f0d",
                "badges": []
            },
            "bpywiki": {
                "site": "bpywiki",
                "title": "\u09ab\u09bf\u09a8\u09b2\u09cd\u09af\u09be\u09a8\u09cd\u09a1",
                "badges": []
            },
            "brwiki": {
                "site": "brwiki",
                "title": "Finland",
                "badges": []
            },
            "bswiki": {
                "site": "bswiki",
                "title": "Finska",
                "badges": []
            },
            "bxrwiki": {
                "site": "bxrwiki",
                "title": "\u0424\u0438\u043d\u043b\u0430\u043d\u0434",
                "badges": []
            },
            "cawiki": {
                "site": "cawiki",
                "title": "Finl\u00e0ndia",
                "badges": []
            },
            "cdowiki": {
                "site": "cdowiki",
                "title": "H\u016dng-l\u00e0ng",
                "badges": []
            },
            "cebwiki": {
                "site": "cebwiki",
                "title": "Finlandia",
                "badges": []
            },
            "cewiki": {
                "site": "cewiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438",
                "badges": []
            },
            "chrwiki": {
                "site": "chrwiki",
                "title": "\u13eb\u13c2\u13a6\u13d9\u13af",
                "badges": []
            },
            "ckbwiki": {
                "site": "ckbwiki",
                "title": "\u0641\u06cc\u0646\u0644\u0627\u0646\u062f",
                "badges": []
            },
            "commonswiki": {
                "site": "commonswiki",
                "title": "Suomi \u2013 Finland",
                "badges": []
            },
            "cowiki": {
                "site": "cowiki",
                "title": "Finlandia",
                "badges": []
            },
            "crhwiki": {
                "site": "crhwiki",
                "title": "Finlandiya",
                "badges": []
            },
            "csbwiki": {
                "site": "csbwiki",
                "title": "Fi\u0144sk\u00f4",
                "badges": []
            },
            "cswiki": {
                "site": "cswiki",
                "title": "Finsko",
                "badges": []
            },
            "cuwiki": {
                "site": "cuwiki",
                "title": "\u0421\u043e\u0443\u043c\u044c",
                "badges": []
            },
            "cvwiki": {
                "site": "cvwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438",
                "badges": []
            },
            "cywiki": {
                "site": "cywiki",
                "title": "Y Ffindir",
                "badges": []
            },
            "dawiki": {
                "site": "dawiki",
                "title": "Finland",
                "badges": [
                    "Q17437798"
                ]
            },
            "dewiki": {
                "site": "dewiki",
                "title": "Finnland",
                "badges": [
                    "Q17437796"
                ]
            },
            "dewikivoyage": {
                "site": "dewikivoyage",
                "title": "Finnland",
                "badges": [
                    "Q17437798"
                ]
            },
            "diqwiki": {
                "site": "diqwiki",
                "title": "Finlanda",
                "badges": []
            },
            "dsbwiki": {
                "site": "dsbwiki",
                "title": "Finska",
                "badges": []
            },
            "dvwiki": {
                "site": "dvwiki",
                "title": "\u078a\u07a8\u0782\u07b0\u078d\u07ad\u0782\u07b0\u0791\u07aa",
                "badges": []
            },
            "dzwiki": {
                "site": "dzwiki",
                "title": "\u0f55\u0f72\u0f53\u0f0b\u0f63\u0f7a\u0f53\u0f4c\u0f0b",
                "badges": []
            },
            "eewiki": {
                "site": "eewiki",
                "title": "Finland",
                "badges": []
            },
            "elwiki": {
                "site": "elwiki",
                "title": "\u03a6\u03b9\u03bd\u03bb\u03b1\u03bd\u03b4\u03af\u03b1",
                "badges": []
            },
            "elwikivoyage": {
                "site": "elwikivoyage",
                "title": "\u03a6\u03b9\u03bd\u03bb\u03b1\u03bd\u03b4\u03af\u03b1",
                "badges": []
            },
            "enwiki": {
                "site": "enwiki",
                "title": "Finland",
                "badges": []
            },
            "enwikivoyage": {
                "site": "enwikivoyage",
                "title": "Finland",
                "badges": []
            },
            "eowiki": {
                "site": "eowiki",
                "title": "Finnlando",
                "badges": []
            },
            "eswiki": {
                "site": "eswiki",
                "title": "Finlandia",
                "badges": []
            },
            "eswikivoyage": {
                "site": "eswikivoyage",
                "title": "Finlandia",
                "badges": []
            },
            "etwiki": {
                "site": "etwiki",
                "title": "Soome",
                "badges": []
            },
            "euwiki": {
                "site": "euwiki",
                "title": "Finlandia",
                "badges": []
            },
            "extwiki": {
                "site": "extwiki",
                "title": "Finl\u00e1ndia",
                "badges": []
            },
            "fawiki": {
                "site": "fawiki",
                "title": "\u0641\u0646\u0644\u0627\u0646\u062f",
                "badges": []
            },
            "fawikivoyage": {
                "site": "fawikivoyage",
                "title": "\u0641\u0646\u0644\u0627\u0646\u062f",
                "badges": []
            },
            "fiu_vrowiki": {
                "site": "fiu_vrowiki",
                "title": "Soom\u00f5",
                "badges": []
            },
            "fiwiki": {
                "site": "fiwiki",
                "title": "Suomi",
                "badges": []
            },
            "fowiki": {
                "site": "fowiki",
                "title": "Finnland",
                "badges": []
            },
            "frpwiki": {
                "site": "frpwiki",
                "title": "Finlande",
                "badges": []
            },
            "frrwiki": {
                "site": "frrwiki",
                "title": "Finl\u00f6nj",
                "badges": []
            },
            "frwiki": {
                "site": "frwiki",
                "title": "Finlande",
                "badges": []
            },
            "frwikivoyage": {
                "site": "frwikivoyage",
                "title": "Finlande",
                "badges": []
            },
            "furwiki": {
                "site": "furwiki",
                "title": "Finlande",
                "badges": []
            },
            "fywiki": {
                "site": "fywiki",
                "title": "Finl\u00e2n",
                "badges": []
            },
            "gagwiki": {
                "site": "gagwiki",
                "title": "Finlandiya",
                "badges": []
            },
            "ganwiki": {
                "site": "ganwiki",
                "title": "\u82ac\u862d",
                "badges": []
            },
            "gawiki": {
                "site": "gawiki",
                "title": "An Fhionlainn",
                "badges": []
            },
            "gdwiki": {
                "site": "gdwiki",
                "title": "Su\u00f2maidh",
                "badges": []
            },
            "glwiki": {
                "site": "glwiki",
                "title": "Finlandia",
                "badges": []
            },
            "gnwiki": {
                "site": "gnwiki",
                "title": "H\u0129landia",
                "badges": []
            },
            "gotwiki": {
                "site": "gotwiki",
                "title": "\ud800\udf46\ud800\udf39\ud800\udf3d\ud800\udf3d\ud800\udf30\ud800\udf3b\ud800\udf30\ud800\udf3d\ud800\udf33",
                "badges": []
            },
            "guwiki": {
                "site": "guwiki",
                "title": "\u0aab\u0ac0\u0aa8\u0ab2\u0ac7\u0a82\u0aa1",
                "badges": []
            },
            "gvwiki": {
                "site": "gvwiki",
                "title": "Finnlynn",
                "badges": []
            },
            "hakwiki": {
                "site": "hakwiki",
                "title": "F\u00fbn-l\u00e0n",
                "badges": []
            },
            "hawiki": {
                "site": "hawiki",
                "title": "Finland",
                "badges": []
            },
            "hawwiki": {
                "site": "hawwiki",
                "title": "Pinilana",
                "badges": []
            },
            "hewiki": {
                "site": "hewiki",
                "title": "\u05e4\u05d9\u05e0\u05dc\u05e0\u05d3",
                "badges": []
            },
            "hewikivoyage": {
                "site": "hewikivoyage",
                "title": "\u05e4\u05d9\u05e0\u05dc\u05e0\u05d3",
                "badges": []
            },
            "hifwiki": {
                "site": "hifwiki",
                "title": "Finland",
                "badges": []
            },
            "hiwiki": {
                "site": "hiwiki",
                "title": "\u092b\u093c\u093f\u0928\u0932\u0948\u0923\u094d\u0921",
                "badges": []
            },
            "hrwiki": {
                "site": "hrwiki",
                "title": "Finska",
                "badges": []
            },
            "hsbwiki": {
                "site": "hsbwiki",
                "title": "Finska",
                "badges": []
            },
            "htwiki": {
                "site": "htwiki",
                "title": "Fenlann",
                "badges": []
            },
            "huwiki": {
                "site": "huwiki",
                "title": "Finnorsz\u00e1g",
                "badges": []
            },
            "hywiki": {
                "site": "hywiki",
                "title": "\u0556\u056b\u0576\u056c\u0561\u0576\u0564\u056b\u0561",
                "badges": []
            },
            "iawiki": {
                "site": "iawiki",
                "title": "Finlandia",
                "badges": []
            },
            "idwiki": {
                "site": "idwiki",
                "title": "Finlandia",
                "badges": []
            },
            "iewiki": {
                "site": "iewiki",
                "title": "Finland",
                "badges": []
            },
            "igwiki": {
                "site": "igwiki",
                "title": "Finland",
                "badges": []
            },
            "ilowiki": {
                "site": "ilowiki",
                "title": "Pinlandia",
                "badges": []
            },
            "iowiki": {
                "site": "iowiki",
                "title": "Finlando",
                "badges": []
            },
            "iswiki": {
                "site": "iswiki",
                "title": "Finnland",
                "badges": [
                    "Q17437798"
                ]
            },
            "itwiki": {
                "site": "itwiki",
                "title": "Finlandia",
                "badges": []
            },
            "itwikiquote": {
                "site": "itwikiquote",
                "title": "Finlandia",
                "badges": []
            },
            "itwikivoyage": {
                "site": "itwikivoyage",
                "title": "Finlandia",
                "badges": []
            },
            "jawiki": {
                "site": "jawiki",
                "title": "\u30d5\u30a3\u30f3\u30e9\u30f3\u30c9",
                "badges": []
            },
            "jbowiki": {
                "site": "jbowiki",
                "title": "gugdrsu,omi",
                "badges": []
            },
            "jvwiki": {
                "site": "jvwiki",
                "title": "Finlandia",
                "badges": []
            },
            "kaawiki": {
                "site": "kaawiki",
                "title": "Finlyandiya",
                "badges": []
            },
            "kawiki": {
                "site": "kawiki",
                "title": "\u10e4\u10d8\u10dc\u10d4\u10d7\u10d8",
                "badges": []
            },
            "kbdwiki": {
                "site": "kbdwiki",
                "title": "\u0424\u0438\u043d\u043b\u044d\u043d\u0434",
                "badges": []
            },
            "kgwiki": {
                "site": "kgwiki",
                "title": "Finlandi",
                "badges": []
            },
            "kiwiki": {
                "site": "kiwiki",
                "title": "Binirandi",
                "badges": []
            },
            "kkwiki": {
                "site": "kkwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "klwiki": {
                "site": "klwiki",
                "title": "Finlandi",
                "badges": []
            },
            "knwiki": {
                "site": "knwiki",
                "title": "\u0cab\u0cbf\u0ca8\u0ccd\u200d\u0cb2\u0ccd\u0caf\u0cbe\u0c82\u0ca1\u0ccd",
                "badges": []
            },
            "koiwiki": {
                "site": "koiwiki",
                "title": "\u0421\u0443\u043e\u043c\u0438",
                "badges": []
            },
            "kowiki": {
                "site": "kowiki",
                "title": "\ud540\ub780\ub4dc",
                "badges": []
            },
            "krcwiki": {
                "site": "krcwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "kuwiki": {
                "site": "kuwiki",
                "title": "F\u00eenlenda",
                "badges": []
            },
            "kvwiki": {
                "site": "kvwiki",
                "title": "\u0421\u0443\u043e\u043c\u0438 \u041c\u0443",
                "badges": []
            },
            "kwwiki": {
                "site": "kwwiki",
                "title": "Pow Finn",
                "badges": []
            },
            "kywiki": {
                "site": "kywiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "ladwiki": {
                "site": "ladwiki",
                "title": "Finlandia",
                "badges": []
            },
            "lawiki": {
                "site": "lawiki",
                "title": "Finnia",
                "badges": []
            },
            "lbwiki": {
                "site": "lbwiki",
                "title": "Finnland",
                "badges": []
            },
            "lezwiki": {
                "site": "lezwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "lgwiki": {
                "site": "lgwiki",
                "title": "Finilandi",
                "badges": []
            },
            "lijwiki": {
                "site": "lijwiki",
                "title": "Finlandia",
                "badges": []
            },
            "liwiki": {
                "site": "liwiki",
                "title": "Finland",
                "badges": []
            },
            "lmowiki": {
                "site": "lmowiki",
                "title": "Finlandia",
                "badges": []
            },
            "lnwiki": {
                "site": "lnwiki",
                "title": "Finilanda",
                "badges": []
            },
            "lowiki": {
                "site": "lowiki",
                "title": "\u0e9b\u0eb0\u0ec0\u0e97\u0e94\u0ec1\u0e9f\u0e87\u0ea5\u0eb1\u0e87",
                "badges": []
            },
            "ltgwiki": {
                "site": "ltgwiki",
                "title": "Suomeja",
                "badges": []
            },
            "ltwiki": {
                "site": "ltwiki",
                "title": "Suomija",
                "badges": [
                    "Q17437798"
                ]
            },
            "lvwiki": {
                "site": "lvwiki",
                "title": "Somija",
                "badges": []
            },
            "mdfwiki": {
                "site": "mdfwiki",
                "title": "\u0421\u0443\u043e\u043c\u0438 \u043c\u0430\u0441\u0442\u043e\u0440",
                "badges": []
            },
            "mgwiki": {
                "site": "mgwiki",
                "title": "Finlandy",
                "badges": []
            },
            "mhrwiki": {
                "site": "mhrwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u0439",
                "badges": []
            },
            "miwiki": {
                "site": "miwiki",
                "title": "Hinerangi",
                "badges": []
            },
            "mkwiki": {
                "site": "mkwiki",
                "title": "\u0424\u0438\u043d\u0441\u043a\u0430",
                "badges": []
            },
            "mlwiki": {
                "site": "mlwiki",
                "title": "\u0d2b\u0d3f\u0d7b\u0d32\u0d3e\u0d28\u0d4d\u0d31\u0d4d",
                "badges": []
            },
            "mnwiki": {
                "site": "mnwiki",
                "title": "\u0424\u0438\u043d\u043b\u0430\u043d\u0434",
                "badges": []
            },
            "mrjwiki": {
                "site": "mrjwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438",
                "badges": []
            },
            "mrwiki": {
                "site": "mrwiki",
                "title": "\u092b\u093f\u0928\u0932\u0902\u0921",
                "badges": []
            },
            "mswiki": {
                "site": "mswiki",
                "title": "Finland",
                "badges": []
            },
            "mtwiki": {
                "site": "mtwiki",
                "title": "Finlandja",
                "badges": []
            },
            "myvwiki": {
                "site": "myvwiki",
                "title": "\u0421\u0443\u043e\u043c\u0438 \u041c\u0430\u0441\u0442\u043e\u0440",
                "badges": []
            },
            "mywiki": {
                "site": "mywiki",
                "title": "\u1016\u1004\u103a\u101c\u1014\u103a\u1014\u102d\u102f\u1004\u103a\u1004\u1036",
                "badges": []
            },
            "mznwiki": {
                "site": "mznwiki",
                "title": "\u0641\u0644\u0627\u0646\u062f",
                "badges": []
            },
            "nahwiki": {
                "site": "nahwiki",
                "title": "Fintl\u0101lpan",
                "badges": []
            },
            "napwiki": {
                "site": "napwiki",
                "title": "Finlandia",
                "badges": []
            },
            "nawiki": {
                "site": "nawiki",
                "title": "Pinrand",
                "badges": []
            },
            "nds_nlwiki": {
                "site": "nds_nlwiki",
                "title": "Finlaand",
                "badges": []
            },
            "ndswiki": {
                "site": "ndswiki",
                "title": "Finnland",
                "badges": []
            },
            "newiki": {
                "site": "newiki",
                "title": "\u092b\u093f\u0928\u0932\u094d\u092f\u093e\u0923\u094d\u0921",
                "badges": []
            },
            "newwiki": {
                "site": "newwiki",
                "title": "\u092b\u093f\u0928\u0932\u094d\u092f\u093e\u0928\u094d\u0921",
                "badges": []
            },
            "nlwiki": {
                "site": "nlwiki",
                "title": "Finland",
                "badges": []
            },
            "nlwikivoyage": {
                "site": "nlwikivoyage",
                "title": "Finland",
                "badges": []
            },
            "nnwiki": {
                "site": "nnwiki",
                "title": "Finland",
                "badges": []
            },
            "novwiki": {
                "site": "novwiki",
                "title": "Finlande",
                "badges": []
            },
            "nowiki": {
                "site": "nowiki",
                "title": "Finland",
                "badges": []
            },
            "nrmwiki": {
                "site": "nrmwiki",
                "title": "F\u00eenlande",
                "badges": []
            },
            "nvwiki": {
                "site": "nvwiki",
                "title": "Nahodits\u02bc\u01eb\u02bc\u0142\u00e1n\u00ed",
                "badges": []
            },
            "ocwiki": {
                "site": "ocwiki",
                "title": "Finl\u00e0ndia",
                "badges": []
            },
            "omwiki": {
                "site": "omwiki",
                "title": "Fiinlaandi",
                "badges": []
            },
            "orwiki": {
                "site": "orwiki",
                "title": "\u0b2b\u0b3f\u0b28\u0b32\u0b4d\u0b5f\u0b3e\u0b23\u0b4d\u0b21",
                "badges": []
            },
            "oswiki": {
                "site": "oswiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438",
                "badges": []
            },
            "pamwiki": {
                "site": "pamwiki",
                "title": "Pinlandya",
                "badges": []
            },
            "papwiki": {
                "site": "papwiki",
                "title": "Finlandia",
                "badges": []
            },
            "pawiki": {
                "site": "pawiki",
                "title": "\u0a2b\u0a3c\u0a3f\u0a28\u0a32\u0a48\u0a02\u0a21",
                "badges": []
            },
            "pcdwiki": {
                "site": "pcdwiki",
                "title": "Finlinde",
                "badges": []
            },
            "pihwiki": {
                "site": "pihwiki",
                "title": "Finland",
                "badges": []
            },
            "plwiki": {
                "site": "plwiki",
                "title": "Finlandia",
                "badges": []
            },
            "plwikiquote": {
                "site": "plwikiquote",
                "title": "Finlandia",
                "badges": []
            },
            "plwikivoyage": {
                "site": "plwikivoyage",
                "title": "Finlandia",
                "badges": []
            },
            "pmswiki": {
                "site": "pmswiki",
                "title": "Finlandia",
                "badges": []
            },
            "pnbwiki": {
                "site": "pnbwiki",
                "title": "\u0641\u0646\u0644\u06cc\u0646\u0688",
                "badges": []
            },
            "pntwiki": {
                "site": "pntwiki",
                "title": "\u03a6\u03b9\u03bd\u03bb\u03b1\u03bd\u03b4\u03af\u03b1",
                "badges": []
            },
            "pswiki": {
                "site": "pswiki",
                "title": "\u0641\u06d0\u0646\u0644\u0627\u0646\u0689",
                "badges": []
            },
            "ptwiki": {
                "site": "ptwiki",
                "title": "Finl\u00e2ndia",
                "badges": []
            },
            "ptwikivoyage": {
                "site": "ptwikivoyage",
                "title": "Finl\u00e2ndia",
                "badges": [
                    "Q17559452"
                ]
            },
            "quwiki": {
                "site": "quwiki",
                "title": "Phinsuyu",
                "badges": []
            },
            "rmwiki": {
                "site": "rmwiki",
                "title": "Finlanda",
                "badges": []
            },
            "rmywiki": {
                "site": "rmywiki",
                "title": "Finland",
                "badges": []
            },
            "roa_rupwiki": {
                "site": "roa_rupwiki",
                "title": "Finlanda",
                "badges": []
            },
            "roa_tarawiki": {
                "site": "roa_tarawiki",
                "title": "Finlandie",
                "badges": []
            },
            "rowiki": {
                "site": "rowiki",
                "title": "Finlanda",
                "badges": []
            },
            "rowikivoyage": {
                "site": "rowikivoyage",
                "title": "Finlanda",
                "badges": []
            },
            "ruewiki": {
                "site": "ruewiki",
                "title": "\u0424\u0456\u043d\u044c\u0441\u043a\u043e",
                "badges": []
            },
            "ruwiki": {
                "site": "ruwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "ruwikivoyage": {
                "site": "ruwikivoyage",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "rwwiki": {
                "site": "rwwiki",
                "title": "Finilande",
                "badges": []
            },
            "sahwiki": {
                "site": "sahwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "sawiki": {
                "site": "sawiki",
                "title": "\u092b\u093f\u0928\u094d\u0932\u0948\u0902\u0921",
                "badges": []
            },
            "scnwiki": {
                "site": "scnwiki",
                "title": "Finlandia",
                "badges": []
            },
            "scowiki": {
                "site": "scowiki",
                "title": "Finland",
                "badges": []
            },
            "scwiki": {
                "site": "scwiki",
                "title": "Finl\u00e0ndia",
                "badges": []
            },
            "sewiki": {
                "site": "sewiki",
                "title": "Suopma",
                "badges": []
            },
            "shwiki": {
                "site": "shwiki",
                "title": "Finska",
                "badges": []
            },
            "simplewiki": {
                "site": "simplewiki",
                "title": "Finland",
                "badges": []
            },
            "siwiki": {
                "site": "siwiki",
                "title": "\u0dc6\u0dd2\u0db1\u0dca\u0dbd\u0db1\u0dca\u0dad\u0dba",
                "badges": []
            },
            "skwiki": {
                "site": "skwiki",
                "title": "F\u00ednsko",
                "badges": [
                    "Q17437796"
                ]
            },
            "slwiki": {
                "site": "slwiki",
                "title": "Finska",
                "badges": []
            },
            "smwiki": {
                "site": "smwiki",
                "title": "Finalagi",
                "badges": []
            },
            "snwiki": {
                "site": "snwiki",
                "title": "Finland",
                "badges": []
            },
            "sowiki": {
                "site": "sowiki",
                "title": "Finland",
                "badges": []
            },
            "sqwiki": {
                "site": "sqwiki",
                "title": "Finlanda",
                "badges": []
            },
            "srnwiki": {
                "site": "srnwiki",
                "title": "Finland",
                "badges": []
            },
            "srwiki": {
                "site": "srwiki",
                "title": "\u0424\u0438\u043d\u0441\u043a\u0430",
                "badges": []
            },
            "sswiki": {
                "site": "sswiki",
                "title": "IFini",
                "badges": []
            },
            "stqwiki": {
                "site": "stqwiki",
                "title": "Finlound",
                "badges": []
            },
            "stwiki": {
                "site": "stwiki",
                "title": "Finland",
                "badges": []
            },
            "suwiki": {
                "site": "suwiki",
                "title": "Finlandia",
                "badges": []
            },
            "svwiki": {
                "site": "svwiki",
                "title": "Finland",
                "badges": []
            },
            "svwikivoyage": {
                "site": "svwikivoyage",
                "title": "Finland",
                "badges": []
            },
            "swwiki": {
                "site": "swwiki",
                "title": "Ufini",
                "badges": []
            },
            "szlwiki": {
                "site": "szlwiki",
                "title": "Finlandyjo",
                "badges": []
            },
            "tawiki": {
                "site": "tawiki",
                "title": "\u0baa\u0bbf\u0ba9\u0bcd\u0bb2\u0bbe\u0ba8\u0bcd\u0ba4\u0bc1",
                "badges": []
            },
            "tetwiki": {
                "site": "tetwiki",
                "title": "Finl\u00e1ndia",
                "badges": []
            },
            "tewiki": {
                "site": "tewiki",
                "title": "\u0c2b\u0c3f\u0c28\u0c4d\u200c\u0c32\u0c3e\u0c02\u0c21\u0c4d",
                "badges": []
            },
            "tgwiki": {
                "site": "tgwiki",
                "title": "\u0424\u0438\u043d\u043b\u0430\u043d\u0434",
                "badges": []
            },
            "thwiki": {
                "site": "thwiki",
                "title": "\u0e1b\u0e23\u0e30\u0e40\u0e17\u0e28\u0e1f\u0e34\u0e19\u0e41\u0e25\u0e19\u0e14\u0e4c",
                "badges": [
                    "Q17437796"
                ]
            },
            "tkwiki": {
                "site": "tkwiki",
                "title": "Finl\u00fdandi\u00fda",
                "badges": []
            },
            "tlwiki": {
                "site": "tlwiki",
                "title": "Finland",
                "badges": []
            },
            "tpiwiki": {
                "site": "tpiwiki",
                "title": "Pinlan",
                "badges": []
            },
            "trwiki": {
                "site": "trwiki",
                "title": "Finlandiya",
                "badges": []
            },
            "ttwiki": {
                "site": "ttwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": [
                    "Q17437798"
                ]
            },
            "twwiki": {
                "site": "twwiki",
                "title": "Finland",
                "badges": []
            },
            "tyvwiki": {
                "site": "tyvwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "udmwiki": {
                "site": "udmwiki",
                "title": "\u0424\u0438\u043d\u043b\u044f\u043d\u0434\u0438\u044f",
                "badges": []
            },
            "ugwiki": {
                "site": "ugwiki",
                "title": "\u0641\u0649\u0646\u0644\u0627\u0646\u062f\u0649\u064a\u06d5",
                "badges": []
            },
            "ukwiki": {
                "site": "ukwiki",
                "title": "\u0424\u0456\u043d\u043b\u044f\u043d\u0434\u0456\u044f",
                "badges": []
            },
            "ukwikivoyage": {
                "site": "ukwikivoyage",
                "title": "\u0424\u0456\u043d\u043b\u044f\u043d\u0434\u0456\u044f",
                "badges": []
            },
            "urwiki": {
                "site": "urwiki",
                "title": "\u0641\u0646 \u0644\u06cc\u0646\u0688",
                "badges": []
            },
            "uzwiki": {
                "site": "uzwiki",
                "title": "Finlandiya",
                "badges": []
            },
            "vecwiki": {
                "site": "vecwiki",
                "title": "Finlandia",
                "badges": []
            },
            "vepwiki": {
                "site": "vepwiki",
                "title": "Suomenma",
                "badges": []
            },
            "viwiki": {
                "site": "viwiki",
                "title": "Ph\u1ea7n Lan",
                "badges": []
            },
            "viwikivoyage": {
                "site": "viwikivoyage",
                "title": "Ph\u1ea7n Lan",
                "badges": []
            },
            "vlswiki": {
                "site": "vlswiki",
                "title": "Finland",
                "badges": []
            },
            "vowiki": {
                "site": "vowiki",
                "title": "Suomiy\u00e4n",
                "badges": []
            },
            "warwiki": {
                "site": "warwiki",
                "title": "Finlandya",
                "badges": []
            },
            "wawiki": {
                "site": "wawiki",
                "title": "Finlande",
                "badges": []
            },
            "wowiki": {
                "site": "wowiki",
                "title": "Finlaand",
                "badges": []
            },
            "wuuwiki": {
                "site": "wuuwiki",
                "title": "\u82ac\u5170",
                "badges": []
            },
            "xalwiki": {
                "site": "xalwiki",
                "title": "\u0421\u0443\u04bb\u043e\u043c\u0443\u0434\u0438\u043d \u041e\u0440\u043d",
                "badges": []
            },
            "xmfwiki": {
                "site": "xmfwiki",
                "title": "\u10e4\u10d8\u10dc\u10d4\u10d7\u10d8",
                "badges": []
            },
            "yiwiki": {
                "site": "yiwiki",
                "title": "\u05e4\u05d9\u05e0\u05dc\u05d0\u05e0\u05d3",
                "badges": []
            },
            "yowiki": {
                "site": "yowiki",
                "title": "F\u00ednl\u00e1nd\u00ec",
                "badges": []
            },
            "zawiki": {
                "site": "zawiki",
                "title": "Finlan",
                "badges": []
            },
            "zeawiki": {
                "site": "zeawiki",
                "title": "Finland",
                "badges": []
            },
            "zh_classicalwiki": {
                "site": "zh_classicalwiki",
                "title": "\u82ac\u862d",
                "badges": [
                    "Q17437798"
                ]
            },
            "zh_min_nanwiki": {
                "site": "zh_min_nanwiki",
                "title": "Suomi",
                "badges": []
            },
            "zh_yuewiki": {
                "site": "zh_yuewiki",
                "title": "\u82ac\u862d",
                "badges": []
            },
            "zhwiki": {
                "site": "zhwiki",
                "title": "\u82ac\u5170",
                "badges": []
            },
            "zhwikivoyage": {
                "site": "zhwikivoyage",
                "title": "\u82ac\u5170",
                "badges": []
            },
            "zuwiki": {
                "site": "zuwiki",
                "title": "IFinlandi",
                "badges": []
            }
        }
    }}';
} 