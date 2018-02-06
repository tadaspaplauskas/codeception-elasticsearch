<?php

namespace Codeception\Module;

use Codeception\Module;
use Codeception\Lib\ModuleContainer;

use Elasticsearch\ClientBuilder;

class Elasticsearch extends Module
{
    public $elasticsearch;

    public function _initialize()
    {
        $clientBuilder = ClientBuilder::create();
        $clientBuilder->setHosts($this->_getConfig('hosts'));
        $this->client = $clientBuilder->build();

        if ($this->_getConfig('cleanup')) {
            $this->client->indices()->delete(['index' => '*']);
        }
    }

    public function grabFromElasticsearch($index = null, $type = null, $queryString = '*')
    {
        $result = $this->client->search(
            [
                'index' => $index,
                'type' => $type,
                'q' => $queryString,
                'size' => 1
            ]
        );

        return !empty($result['hits']['hits'])
            ? $result['hits']['hits'][0]['_source']
            : array();
    }

    public function seeInElasticsearch($index, $type, $fieldsOrValue = null)
    {
        return $this->assertTrue($this->count($index, $type, $fieldsOrValue) > 0, 'item exists');
    }

    public function dontSeeInElasticsearch($index, $type, $fieldsOrValue = null)
    {
        return $this->assertTrue($this->count($index, $type, $fieldsOrValue) === 0,
            'item does not exist');
    }

    protected function count($index, $type, $fieldsOrValue = null)
    {
        $query = [];

        if (empty($fieldsOrValue)) {
            $query = [ 'match_all' => [] ];
        }
        elseif (is_array($fieldsOrValue)) {
            $query['bool']['filter'] = array_map(function ($value, $key) {
                return ['match' => [$key => $value]];
            }, $fieldsOrValue, array_keys($fieldsOrValue));
        }
        else {
            $query['multi_match'] = [
                'query' => $fieldsOrValue,
                'fields' => '_all',
            ];
        }

        $params = [
            'index' => $index,
            'type' => $type,
            'size' => 0,
            'body' => ['query' => $query],
        ];

        $this->client->indices()->refresh();

        $result = $this->client->search($params);

        return (int) $result['hits']['total'];
    }

    public function haveInElasticsearch($document)
    {
        $result = $this->client->index($document);

        $this->client->indices()->refresh();

        return $result;
    }

}
