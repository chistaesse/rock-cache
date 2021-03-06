<?php
namespace rock\cache;

use rock\base\BaseException;
use rock\events\EventsInterface;
use rock\helpers\Instance;
use rock\mongodb\Query;

/**
 * Cache implements a cache application component by storing cached data in a MongoDB.
 *
 * By default, Cache stores session data in a MongoDB collection named 'cache' inside the default database.
 * This collection is better to be pre-created with fields 'id' and 'expire' indexed.
 * The collection name can be changed by setting {@see \rock\cache\MongoCache::$cacheCollection}.
 *
 * Please refer to {@see \rock\cache\CacheInterface} for common cache operations that are supported by Cache.
 *
 * The following example shows how you can configure the application to use Cache:
 *
 * ```php
 * 'cache' => [
 *     'class' => 'rock\cache\MongoCache',
 *     // 'storage' => 'mymongodb',
 *     // 'cacheCollection' => 'my_cache',
 * ]
 * ```
 *
 */
class MongoCache extends Cache implements CacheInterface, EventsInterface
{
    /**
     * @var \rock\mongodb\Connection|string the MongoDB connection object or the application component ID of the MongoDB connection.
     * After the Cache object is created, if you want to change this property, you should only assign it
     * with a MongoDB connection object.
     */
    public $storage = 'mongodb';
    /**
     * @var string|array the name of the MongoDB collection that stores the cache data.
     * Please refer to {@see \rock\mongodb\Connection::getCollection()} on how to specify this parameter.
     * This collection is better to be pre-created with fields 'id' and 'expire' indexed.
     */
    public $cacheCollection = 'cache';

    public function init()
    {
        $this->parentInit();
        $this->storage = Instance::ensure($this->storage);
    }

    /**
     * {@inheritdoc}
     *
     * @return \rock\mongodb\Connection
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value = null, $expire = 0, array $tags = [])
    {
        if (empty($key)) {
            return false;
        }

        $result = $this->updateInternal(
            $this->prepareKey($key),
            [
                'expire' => $expire > 0 ? new \MongoDate($expire + time()) : null,
                'value' => $value,
                'tags' => $this->prepareTags($tags)
            ]
        );

        if ($result) {
            return true;
        } else {
            return $this->add($key, $value, $expire, $tags);
        }
    }

    protected function updateInternal($key, $data)
    {
        return $this->storage->getCollection($this->cacheCollection)
            ->update(['id' => $key], $data);
    }

    /**
     * @inheritdoc
     */
    public function add($key, $value = null, $expire = 0, array $tags = [])
    {
        if (empty($key)) {
            return false;
        }

        return $this->insertInternal(
            [
                'id' => $this->prepareKey($key),
                'expire' => $expire > 0 ? new \MongoDate($expire + time()) : null,
                'value' => $value,
                'tags' => $this->prepareTags($tags)
            ]
        );
    }

    protected function insertInternal($data)
    {
        try {
            $this->storage
                ->getCollection($this->cacheCollection)
                ->insert($data);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function get($key)
    {
        $key = $this->prepareKey($key);
        $query = new Query;
        $row = $query->select(['value'])
            ->from($this->cacheCollection)
            ->where([
                'id' => $key,
                '$or' => [
                    [
                        'expire' => null
                    ],
                    [
                        'expire' => ['$gt' => new \MongoDate()]
                    ],
                ],
            ])
            ->one($this->storage);

        if (empty($row)) {
            return false;
        }

        return $row['value'] === '' ? null : $row['value'];
    }

    /**
     * @inheritdoc
     */
    public function exists($key)
    {
        $query = new Query;
        return $query
            ->from($this->cacheCollection)
            ->where([
                'id' => $this->prepareKey($key),
                '$or' => [
                    [
                        'expire' => null
                    ],
                    [
                        'expire' => ['$gt' => new \MongoDate()]
                    ],
                ],
            ])
            ->exists($this->storage);
    }

    /**
     * @inheritdoc
     */
    public function increment($key, $offset = 1, $expire = 0, $create = true)
    {
        $condition = [
            'id' => $this->prepareKey($key),
            '$or' => [
                [
                    'expire' => null
                ],
                [
                    'expire' => ['$gt' => new \MongoDate()]
                ],
            ],
        ];
        $update = [
            '$inc' => ['value' => $offset],
            '$set' => ['expire' => $expire > 0 ? new \MongoDate($expire + time()) : null]
        ];
        $fields = ['value' => 1];
        $options = $create === true ? ['new' => true, 'upsert' => true] : ['new' => true];
        if (!$row = $this->storage->getCollection($this->cacheCollection)
            ->findAndModify($condition, $update, $fields, $options)
        ) {
            return false;
        }

        return $row['value'];
    }

    /**
     * @inheritdoc
     */
    public function decrement($key, $offset = 1, $expire = 0, $create = true)
    {
        $condition = [
            'id' => $this->prepareKey($key),
            '$or' => [
                [
                    'expire' => null
                ],
                [
                    'expire' => ['$gt' => new \MongoDate()]
                ],
            ],
        ];
        $update = [
            '$inc' => ['value' => -1 * $offset],
            '$set' => ['expire' => $expire > 0 ? new \MongoDate($expire + time()) : null]
        ];
        $fields = ['value' => 1];
        $options = $create === true ? ['new' => true, 'upsert' => true] : ['new' => true];
        if (!$row = $this->storage->getCollection($this->cacheCollection)
            ->findAndModify($condition, $update, $fields, $options)
        ) {
            return false;
        }

        return $row['value'];
    }

    /**
     * @inheritdoc
     */
    public function getTag($tag)
    {
        throw new CacheException(CacheException::UNKNOWN_METHOD, ['method' => __METHOD__]);
    }

    /**
     * @inheritdoc
     */
    public function removeTag($tag)
    {
        return (bool)$this->storage->getCollection($this->cacheCollection)
            ->remove(['tags' => $this->prepareTag($tag)]);
    }

    /**
     * @inheritdoc
     */
    public function getMulti(array $keys)
    {
        $keys = $this->prepareKeys($keys);
        $query = new Query;
        $rows = $query->select(['id', 'value'])
            ->from($this->cacheCollection)
            ->where([
                'id' => ['$in' => $keys],
                '$or' => [
                    [
                        'expire' => null
                    ],
                    [
                        'expire' => ['$gt' => new \MongoDate()]
                    ],
                ],
            ])
            ->indexBy('id')
            ->all($this->storage);

        if (empty($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $key => $value) {
            $result[$key] = $value['value'] === '' ? null : $value['value'];
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function touch($key, $expire = 0)
    {
        $result = $this->updateInternal(
            $this->prepareKey($key),
            [
                'expire' => $expire > 0 ? new \MongoDate($expire + time()) : null,
            ]
        );

        if ($result) {
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function touchMulti(array $keys, $expire = 0)
    {
        return (bool)$this->storage->getCollection($this->cacheCollection)
            ->update(
                ['id' => ['$in' => $this->prepareKeys($keys)],
                    '$or' => [
                        [
                            'expire' => null
                        ],
                        [
                            'expire' => ['$gt' => new \MongoDate()]
                        ],
                    ],
                ],
                ['expire' => $expire > 0 ? new \MongoDate($expire + time()) : null]
            );
    }

    /**
     * @inheritdoc
     */
    public function remove($key)
    {
        return (bool)$this->storage->getCollection($this->cacheCollection)
            ->remove(['id' => $this->prepareKey($key)]);
    }

    /**
     * @inheritdoc
     */
    public function removeMulti(array $keys)
    {
        $this->storage->getCollection($this->cacheCollection)
            ->remove(['id' => ['$in' => $this->prepareKeys($keys)]]);
    }

    /**
     * @inheritdoc
     */
    public function getMultiTags(array $tags)
    {
        throw new CacheException(CacheException::UNKNOWN_METHOD, ['method' => __METHOD__]);
    }

    /**
     * @inheritdoc
     */
    public function existsTag($tag)
    {
        throw new CacheException(CacheException::UNKNOWN_METHOD, ['method' => __METHOD__]);
    }

    /**
     * @inheritdoc
     */
    public function removeMultiTags(array $tags)
    {
        $this->storage->getCollection($this->cacheCollection)
            ->remove(['tags' => ['$in' => $this->prepareTags($tags)]]);
    }

    /**
     * @inheritdoc
     */
    public function getAllKeys($limit = 1000)
    {
        return array_keys($this->getAll($limit));
    }

    /**
     * @inheritdoc
     */
    public function getAll($limit = 1000)
    {
        $cursor = $this->storage->getCollection($this->cacheCollection)->find([], ['id', 'value'])->limit($limit);
        $result = [];
        foreach ($cursor as $data) {
            $result[$data['id']] = $data['value'];
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function lock($key, $iteration = 15)
    {
        $i = 0;

        while (!$this->add($this->prepareKey($key, self::LOCK_PREFIX), 1, $this->lockExpire)) {
            $i++;
            if ($i > $iteration) {
                if (class_exists('\rock\log\Log')) {
                    $message = BaseException::convertExceptionToString(new CacheException(CacheException::INVALID_SAVE, ['key' => $key]));
                    Log::err($message);
                }
                return false;
            }
            usleep(rand(10, 1000));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function unlock($key)
    {
        return (bool)$this->storage->getCollection($this->cacheCollection)
            ->remove(['id' => $this->prepareKey($key, self::LOCK_PREFIX)]);
    }

    /**
     * @inheritdoc
     */
    public function flush()
    {
        $this->storage->getCollection($this->cacheCollection)
            ->remove();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function status()
    {
        throw new CacheException(CacheException::UNKNOWN_METHOD, ['method' => __METHOD__]);
    }
}