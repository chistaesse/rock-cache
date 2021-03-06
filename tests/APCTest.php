<?php
namespace rockunit;


use rock\cache\APC;
use rock\cache\CacheInterface;

/**
 * @group cache
 * @group apc
 */
class APCTest extends CommonCache
{
    public function setUp()
    {
        if (!extension_loaded('apc')) {
            $this->markTestSkipped(
                'The APC is not available.'
            );
        }

        (new APC())->flush();
    }

    public function init($serialize)
    {
        if (!extension_loaded('apc')) {
            $this->markTestSkipped(
                'The APC is not available.'
            );
        }
        $cache = new APC(['serializer' => $serialize]);
        return $cache;
    }

    /**
     * @dataProvider providerCache
     * @expectedException \rock\cache\CacheException
     */
    public function testGetStorage(CacheInterface $cache)
    {
        $cache->getStorage();
    }

    /**
     * @dataProvider providerCache
     */
    public function testTtl(CacheInterface $cache)
    {
        $this->markTestSkipped('Skipping: ' . __METHOD__);
    }

    /**
     * @dataProvider providerCache
     */
    public function testExistsByTouchFalse(CacheInterface $cache)
    {
        $this->markTestSkipped('Skipping: ' . __METHOD__);
    }

    /**
     * @dataProvider providerCache
     */
    public function testTouch(CacheInterface $cache)
    {
        $this->markTestSkipped('Skipping: ' . __METHOD__);
    }

    /**
     * @dataProvider providerCache
     */
    public function testTouchMultiTrue(CacheInterface $cache)
    {
        $this->markTestSkipped('Skipping: ' . __METHOD__);
    }

    /**
     * @dataProvider providerCache
     */
    public function testTouchMultiFalse(CacheInterface $cache)
    {
        $this->markTestSkipped('Skipping: ' . __METHOD__);
    }

    /**
     * @dataProvider providerCache
     */
    public function testIncrementWithTtl(CacheInterface $cache)
    {
        $this->markTestSkipped('Skipping: ' . __METHOD__);
    }
}
 