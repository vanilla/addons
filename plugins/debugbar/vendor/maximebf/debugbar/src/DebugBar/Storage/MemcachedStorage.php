<?php
/*
 * This file is part of the DebugBar package.
 *
 * (c) 2013 Maxime Bouroumeau-Fuseau
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DebugBar\Storage;

use Memcached;

/**
 * Stores collected data into Memcache using the Memcached extension
 */
class MemcachedStorage implements StorageInterface
{
    protected $memcached;

    protected $keyNamespace;

    /**
     * @param Memcached $memcached
     */
    public function __construct(Memcached $memcached, $keyNamespace = 'phpdebugbar')
    {
        $this->memcached = $memcached;
        $this->keyNamespace = $keyNamespace;
    }

    /**
     * {@inheritdoc}
     */
    public function save($id, $data)
    {
        $key = $this->createKey($id);
        $this->memcached->set($key, $data);
        if (!$this->memcached->append($this->keyNamespace, "|$key")) {
            $this->memcached->set($this->keyNamespace, $key);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        return $this->memcached->get($this->createKey($id));
    }

    /**
     * {@inheritdoc}
     */
    public function find(array $filters = [], $max = 20, $offset = 0)
    {
        if (!($keys = $this->memcached->get($this->keyNamespace))) {
            return [];
        }

        $results = [];
        foreach (explode('|', $keys) as $key) {
            if ($data = $this->memcached->get($key)) {
                $meta = $data['__meta'];
                if ($this->filter($meta, $filters)) {
                    $results[] = $meta;
                }
            }
        }
        return array_slice($results, $offset, $max);
    }

    /**
     * Filter the metadata for matches.
     * 
     * @param  array $meta
     * @param  array $filters
     * @return bool
     */
    protected function filter($meta, $filters)
    {
        foreach ($filters as $key => $value) {
            if (!isset($meta[$key]) || fnmatch($value, $meta[$key]) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        if (!($keys = $this->memcached->get($this->keyNamespace))) {
            return;
        }
        $this->memcached->delete($this->keyNamespace);
        $this->memcached->deleteMulti(explode('|', $keys));
    }

    /**
     * @param  string $id
     * @return string 
     */
    protected function createKey($id)
    {
        return md5("{$this->keyNamespace}.$id");
    }
}
