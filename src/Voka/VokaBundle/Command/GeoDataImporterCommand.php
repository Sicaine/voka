<?php
namespace Voka\VokaBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GeoDataImporterCommand extends ContainerAwareCommand{
    protected function configure(){
        $this->setName('voka:import:geodata');
    }

    protected function execute(InputInterface $input, OutputInterface $output){

        /* gets the data from a URL */
        function get_data($url) {
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;
        }

        echo '.';
        $content = get_data('http://wdq.wmflabs.org/api?q=TREE[6256][][31]');
        echo '.';
        $result = json_decode($content);
        echo substr($content, 50)."\n";

        $countries = [];
#var_dump($result);
        foreach($result->items as $item) {
            $countries['Q'.$item] = [];
        }

        $ids = [];

        $i = 1;
        foreach($countries as $key=>$value){
            $ids[] = $key;
            if($i % 50 == 0){
                extractData($ids);
                unset($ids);
                $ids = [];
                $i = 0;
            }
            $i++;
        }
        if(count($ids) > 0)
            extractData($ids);

        function extractData($ids){
            global $countries;
            $idString = implode($ids, '|');
            echo 'extract Data for: '.$idString;
            $result = get_data('https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.$idString);
            $entities = json_decode($result);

            echo "\n";
            foreach($entities->entities as $key=>$value){
                echo '.';
                $countries[$key] = $value;
            }
        }

        echo "\n";
        echo json_encode($countries, JSON_PRETTY_PRINT);





        $output->writeln("blub");
    }

} 