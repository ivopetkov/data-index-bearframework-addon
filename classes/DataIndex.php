<?php

/*
 * Data Index addon for Bear Framework
 * https://github.com/ivopetkov/data-index-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

use BearFramework\App;

/**
 * 
 */
class DataIndex
{

    /**
     * 
     * @param string $indexID
     * @param string $key
     * @param array $data
     */
    public function set(string $indexID, string $key, array $data): void
    {
        $this->setMultiple($indexID, [['key' => $key, 'data' => $data]]);
    }

    /**
     * 
     * @param string $indexID
     * @param array $items
     */
    public function setMultiple(string $indexID, array $items): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('');
            }
            if (!isset($item['key']) || !is_string($item['key'])) {
                throw new \InvalidArgumentException('');
            }
            if (!isset($item['data']) || !is_array($item['data'])) {
                throw new \InvalidArgumentException('');
            }
        }
        $this->acquireLock($indexID);
        $indexData = $this->getIndexData($indexID);
        if (!isset($indexData['id'])) {
            $indexData['id'] = $indexID;
        }
        if (!isset($indexData['keys'])) {
            $indexData['keys'] = [];
        }

        $changedChunks = [];
        $chunksData = [];
        foreach ($items as $item) {
            $key = $item['key'];
            $data = $item['data'];
            $chunkIndex = isset($indexData['keys'][$key]) ? $indexData['keys'][$key][1] : null;
            if ($chunkIndex === null) {
                for ($i = 0; $i < 10000; $i++) {
                    if (!isset($chunksData[$i])) {
                        $chunksData[$i] = $this->getChunkData($indexID, $i);
                    }
                    if (strlen(serialize($chunksData[$i])) < 900000) {
                        $chunkIndex = $i;
                        break;
                    }
                }
            }
            $chunksData[$chunkIndex][$key] = $data;
            $indexData['keys'][$key] = $chunkIndex;
            $changedChunks[$chunkIndex] = true;
        }
        foreach ($chunksData as $chunkIndex => $chunkData) {
            if (isset($changedChunks[$chunkIndex])) {
                $this->setChunkData($indexID, $chunkIndex, $chunkData);
            }
        }
        $this->setIndexData($indexID, $indexData);
        $this->releaseLock($indexID);
    }

    /**
     * 
     * @param string $indexID
     * @return \IvoPetkov\DataList
     */
    public function getList(string $indexID): \IvoPetkov\DataList
    {
        return new \IvoPetkov\DataList(function (\IvoPetkov\DataListContext $context) use ($indexID) {
            $result = [];
            $indexData = $this->getIndexData($indexID);
            asort($indexData);
            if (isset($indexData['keys'])) {
                $previousChunkIndex = null;
                $chunkData = null;
                foreach ($indexData['keys'] as $key => $chunkIndex) {
                    if ($previousChunkIndex !== $chunkIndex) {
                        $chunkData = $this->getChunkData($indexID, $chunkIndex);
                        $previousChunkIndex = $chunkIndex;
                    }
                    $result[] = array_merge((isset($chunkData[$key]) ? $chunkData[$key] : []), ['__key' => $key]);
                }
            }
            return $result;
        });
    }

    /**
     * 
     * @param string $indexID
     * @param string $key
     * @return ?\IvoPetkov\DataObject
     */
    public function get(string $indexID, string $key): ?\IvoPetkov\DataObject
    {
        $indexData = $this->getIndexData($indexID);
        if (isset($indexData['keys']) && isset($indexData['keys'][$key])) {
            $chunkData = $this->getChunkData($indexID, $indexData['keys'][$key]);
            return new \IvoPetkov\DataObject(array_merge((isset($chunkData[$key]) ? $chunkData[$key] : []), ['__key' => $key]));
        }
        return null;
    }

    /**
     * 
     * @param string $indexID
     * @param string $key
     * @return boolean
     */
    public function exists(string $indexID, string $key): bool
    {
        $indexData = $this->getIndexData($indexID);
        if (isset($indexData['keys']) && isset($indexData['keys'][$key])) {
            $chunkData = $this->getChunkData($indexID, $indexData['keys'][$key]);
            return isset($chunkData[$key]);
        }
        return false;
    }

    /**
     * 
     * @param string $indexID
     * @return array
     */
    public function getKeys(string $indexID): array
    {
        $indexData = $this->getIndexData($indexID);
        return isset($indexData['keys']) ? array_keys($indexData['keys']) : [];
    }

    /**
     * 
     * @param string $indexID
     * @param string $key
     */
    public function delete(string $indexID, string $key): void
    {
        $this->deleteMultiple($indexID, [$key]);
    }

    /**
     * 
     * @param string $indexID
     * @param array $keys
     * @throws \InvalidArgumentException
     */
    public function deleteMultiple(string $indexID, array $keys): void
    {
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('');
            }
        }
        $this->acquireLock($indexID);
        $indexData = $this->getIndexData($indexID);
        $chunksData = [];
        foreach ($keys as $key) {
            if (isset($indexData['keys'], $indexData['keys'][$key])) {
                $chunkIndex = $indexData['keys'][$key];
                unset($indexData['keys'][$key]);
                if (!isset($chunksData[$chunkIndex])) {
                    $chunksData[$chunkIndex] = $this->getChunkData($indexID, $chunkIndex);
                }
                if (isset($chunksData[$chunkIndex][$key])) {
                    unset($chunksData[$chunkIndex][$key]);
                }
            }
        }
        $this->setIndexData($indexID, $indexData);
        foreach ($chunksData as $chunkIndex => $chunkData) {
            $this->setChunkData($indexID, $chunkIndex, $chunkData);
        }
        $this->releaseLock($indexID);
    }

    /**
     * 
     * @param string $indexID
     * @param int $index
     * @return array
     */
    private function getChunkData(string $indexID, int $index): array
    {
        $app = App::get();
        $dataKey = 'ivopetkov-data-index/' . md5($indexID) . '.' . $index;
        $value = $app->data->getValue($dataKey);
        return $value !== null ? unserialize(gzuncompress($value)) : [];
    }

    /**
     * 
     * @param string $indexID
     * @param int $index
     * @param array $data
     */
    private function setChunkData(string $indexID, int $index, array $data): void
    {
        $app = App::get();
        $dataKey = 'ivopetkov-data-index/' . md5($indexID) . '.' . $index;
        $app->data->set($app->data->make($dataKey, gzcompress(serialize($data), 9)));
    }

    /**
     * 
     * @param string $indexID
     * @return array
     */
    private function getIndexData(string $indexID): array
    {
        $app = App::get();
        $dataKey = 'ivopetkov-data-index/' . md5($indexID);
        $value = $app->data->getValue($dataKey);
        return $value !== null ? unserialize(gzuncompress($value)) : [];
    }

    /**
     * 
     * @param string $indexID
     * @param array $data
     */
    private function setIndexData(string $indexID, array $data): void
    {
        $app = App::get();
        $dataKey = 'ivopetkov-data-index/' . md5($indexID);
        $app->data->set($app->data->make($dataKey, gzcompress(serialize($data), 9)));
    }

    /**
     * 
     * @param string $indexID
     */
    private function acquireLock(string $indexID): void
    {
        $app = App::get();
        $app->locks->acquire('data-index-' . $indexID, ['timeout' => 60 * 60]);
    }

    /**
     * 
     * @param string $indexID
     */
    private function releaseLock(string $indexID): void
    {
        $app = App::get();
        $app->locks->release('data-index-' . $indexID);
    }
}
