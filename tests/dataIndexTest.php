<?php

/*
 * Data Index addon for Bear Framework
 * https://github.com/ivopetkov/data-index-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class DataIndexTest extends BearFramework\AddonTests\PHPUnitTestCase
{

    /**
     * 
     */
    public function testSearch()
    {
        $app = $this->getApp();

        for ($i = 0; $i < 150; $i++) {
            $itemID = 'item' . $i;
            $data = [
                'value' => $i . str_repeat('0123456789', 1000)
            ];
            $app->dataIndex->set('index1', $itemID, $data);
        }

        $this->assertTrue($app->dataIndex->getList('index1')->length === 150);

        $list = $app->dataIndex->getList('index1')
                ->filterBy('value', '129', 'startWith');
        $this->assertTrue($list->length === 1);
        $this->assertTrue($list[0]->__key === 'item129');
    }

    /**
     * 
     */
    public function testGetKeys()
    {
        $app = $this->getApp();

        $app->dataIndex->set('index1', 'item1', ['value' => 1]);
        $app->dataIndex->set('index1', 'item2', ['value' => 2]);
        $app->dataIndex->set('index2', 'item3', ['value' => 3]);

        $keys = $app->dataIndex->getKeys('index1');
        $this->assertTrue(sizeof($keys) === 2);
        $this->assertTrue($keys[0] === 'item1');
        $this->assertTrue($keys[1] === 'item2');

        $keys = $app->dataIndex->getKeys('index2');
        $this->assertTrue(sizeof($keys) === 1);
        $this->assertTrue($keys[0] === 'item3');
    }

    /**
     * 
     */
    public function testDelete()
    {
        $app = $this->getApp();

        $app->dataIndex->set('index1', 'item1', ['value' => 1]);
        $app->dataIndex->set('index1', 'item2', ['value' => 2]);
        $app->dataIndex->set('index1', 'item3', ['value' => 3]);

        $keys = $app->dataIndex->getKeys('index1');
        $this->assertTrue(sizeof($keys) === 3);
        $this->assertTrue($keys[0] === 'item1');
        $this->assertTrue($keys[1] === 'item2');
        $this->assertTrue($keys[2] === 'item3');

        $app->dataIndex->delete('index1', 'item2');

        $keys = $app->dataIndex->getKeys('index1');
        $this->assertTrue(sizeof($keys) === 2);
        $this->assertTrue($keys[0] === 'item1');
        $this->assertTrue($keys[1] === 'item3');
    }

    /**
     * 
     */
    public function testGetAndExists()
    {
        $app = $this->getApp();

        $app->dataIndex->set('index1', 'item1', ['value' => 1]);
        $app->dataIndex->set('index1', 'item2', ['value' => 2]);
        $app->dataIndex->set('index1', 'item3', ['value' => 3]);

        $this->assertTrue($app->dataIndex->exists('index1', 'item1'));
        $item = $app->dataIndex->get('index1', 'item1');
        $this->assertTrue($item->value === 1);

        $this->assertFalse($app->dataIndex->exists('index1', 'item4'));
        $item = $app->dataIndex->get('index1', 'item4');
        $this->assertTrue($item === null);
    }

}
