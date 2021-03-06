<?php

namespace MongoDB\Tests\Operation;

use MongoDB\BSON\Javascript;
use MongoDB\Driver\BulkWrite;
use MongoDB\Operation\Find;
use MongoDB\Operation\MapReduce;

class MapReduceFunctionalTest extends FunctionalTestCase
{
    public function testResult()
    {
        $this->createFixtures(3);

        $map = new Javascript('function() { emit(this.x, this.y); }');
        $reduce = new Javascript('function(key, values) { return Array.sum(values); }');
        $out = ['inline' => 1];

        $operation = new MapReduce($this->getDatabaseName(), $this->getCollectionName(), $map, $reduce, $out);
        $result = $operation->execute($this->getPrimaryServer());

        $this->assertInstanceOf('MongoDB\MapReduceResult', $result);
        $this->assertGreaterThanOrEqual(0, $result->getExecutionTimeMS());
        $this->assertNotEmpty($result->getCounts());
        $this->assertNotEmpty($result->getTiming());
    }

    public function testResultDoesNotIncludeTimingWithoutVerboseOption()
    {
        $this->createFixtures(3);

        $map = new Javascript('function() { emit(this.x, this.y); }');
        $reduce = new Javascript('function(key, values) { return Array.sum(values); }');
        $out = ['inline' => 1];

        $operation = new MapReduce($this->getDatabaseName(), $this->getCollectionName(), $map, $reduce, $out, ['verbose' => false]);
        $result = $operation->execute($this->getPrimaryServer());

        $this->assertInstanceOf('MongoDB\MapReduceResult', $result);
        $this->assertGreaterThanOrEqual(0, $result->getExecutionTimeMS());
        $this->assertNotEmpty($result->getCounts());
        $this->assertEmpty($result->getTiming());
    }

    /**
     * @dataProvider provideTypeMapOptionsAndExpectedDocuments
     */
    public function testTypeMapOptionWithInlineResults(array $typeMap = null, array $expectedDocuments)
    {
        $this->createFixtures(3);

        $map = new Javascript('function() { emit(this.x, this.y); }');
        $reduce = new Javascript('function(key, values) { return Array.sum(values); }');
        $out = ['inline' => 1];

        $operation = new MapReduce($this->getDatabaseName(), $this->getCollectionName(), $map, $reduce, $out, ['typeMap' => $typeMap]);
        $results = iterator_to_array($operation->execute($this->getPrimaryServer()));

        $this->assertEquals($expectedDocuments, $results);
    }

    public function provideTypeMapOptionsAndExpectedDocuments()
    {
        return [
            [
                null,
                [
                    (object) ['_id' => 1, 'value' => 3],
                    (object) ['_id' => 2, 'value' => 6],
                    (object) ['_id' => 3, 'value' => 9],
                ],
            ],
            [
                ['root' => 'array'],
                [
                    ['_id' => 1, 'value' => 3],
                    ['_id' => 2, 'value' => 6],
                    ['_id' => 3, 'value' => 9],
                ],
            ],
            [
                ['root' => 'object'],
                [
                    (object) ['_id' => 1, 'value' => 3],
                    (object) ['_id' => 2, 'value' => 6],
                    (object) ['_id' => 3, 'value' => 9],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideTypeMapOptionsAndExpectedDocuments
     */
    public function testTypeMapOptionWithOutputCollection(array $typeMap = null, array $expectedDocuments)
    {
        $this->createFixtures(3);

        $map = new Javascript('function() { emit(this.x, this.y); }');
        $reduce = new Javascript('function(key, values) { return Array.sum(values); }');
        $out = $this->getCollectionName() . '.output';

        $operation = new MapReduce($this->getDatabaseName(), $this->getCollectionName(), $map, $reduce, $out, ['typeMap' => $typeMap]);
        $results = iterator_to_array($operation->execute($this->getPrimaryServer()));

        $this->assertEquals($expectedDocuments, $results);

        $operation = new Find($this->getDatabaseName(), $out, [], ['typeMap' => $typeMap]);
        $cursor = $operation->execute($this->getPrimaryServer());

        $this->assertEquals($expectedDocuments, iterator_to_array($cursor));
    }

    /**
     * Create data fixtures.
     *
     * @param integer $n
     */
    private function createFixtures($n)
    {
        $bulkWrite = new BulkWrite(['ordered' => true]);

        for ($i = 1; $i <= $n; $i++) {
            $bulkWrite->insert(['x' => $i, 'y' => $i]);
            $bulkWrite->insert(['x' => $i, 'y' => $i * 2]);
        }

        $result = $this->manager->executeBulkWrite($this->getNamespace(), $bulkWrite);

        $this->assertEquals($n * 2, $result->getInsertedCount());
    }
}
