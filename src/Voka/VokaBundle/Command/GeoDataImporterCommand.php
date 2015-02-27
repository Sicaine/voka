<?php
namespace Voka\VokaBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GeoDataImporterCommand extends ContainerAwareCommand{

    private $countries = [];

    protected function configure(){
        $this->setName('voka:import:geodata');
    }

    protected function execute(InputInterface $input, OutputInterface $output){
        $output->writeln("Retrieving JSON Data from wmflabs");

        $content = $this->getRAWDataFromUrl('http://wdq.wmflabs.org/api?q=TREE[6256][][31]');

        $output->writeln("... retrieved.");

        $output->writeln("Start decode JSON");
        $result = json_decode($content);
        $output->writeln("finished decode JSON");

        $this->countries = [];

        foreach($result->items as $item) {
            $this->countries['Q'.$item] = [];
        }

        $ids = [];

        $i = 1;
        foreach($this->countries as $key=>$value){
            $ids[] = $key;
            if($i % 50 == 0){
                $this->extractData($output, $ids);
                unset($ids);
                $ids = [];
                $i = 0;
            }
            $i++;
        }
        if(count($ids) > 0)
            $this->extractData($output, $ids);


        echo "\n";
        echo json_encode($this->countries, JSON_PRETTY_PRINT);

        $output->writeln("Done");
    }

    private function getRAWDataFromUrl($url) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private function extractData(OutputInterface $output, $ids){
        $idString = implode($ids, '|');
        $output->writeln('extract Data for: '.$idString);
        $result = $this->getRAWDataFromUrl('https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.$idString);
        $entities = json_decode($result);

        foreach($entities->entities as $key=>$value){
            $output->write('.');
            $this->countries[$key] = $value;
        }

        $output->writeln('');
    }
} 