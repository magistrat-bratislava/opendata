<?php

namespace App\Model;

use Nette;
use Nette\Caching\Cache;
use Nette\Database\Context;

class LastOnlinedataControl
{
    use Nette\SmartObject;

    private $db;
    private $cache;

    public function __construct(Context $database)
    {
        $this->db = $database;

        $storage = new Nette\Caching\Storages\FileStorage('/tmp');
        $this->cache = new Cache($storage);
    }

    public function loadFromCache($key)
    {
        if (($val = $this->cache->load($key)) !== NULL) {
            if (isset($val['date']) && time() < strtotime($val['date']) + 60 * 60 * 24)
                return $val;
        }
        return NULL;
    }

    public function formatAndSaveCache($key, $data, $col)
    {
        $save = [
            'date' => date('Y-m-d') . ' 08:00:00',
            'names' => [],
            'val' => []
        ];

        foreach ($data as $d) {
            if ($d[$col] == "Nan")
                $save['names'][] = "neuvedené";
            else
                $save['names'][] = $d[$col];
            $save['val'][] = $d['c'];
        }

        $this->cache->remove($key);
        $this->cache->save($key, $save);

        return $save;
    }

    public function getLastDevices()
    {
        return (($val = $this->loadFromCache('last_devices')) !== NULL ? $val : $this->saveLastDevices());
    }

    public function saveLastDevices()
    {
        $data = $this->db->table('onlinedata_form')
            ->select('name, SUM(value) AS c')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->where('type', 'device_type')
            ->group('name')
            ->order('c DESC')
            ->limit(10)
            ->fetchAll();

        return $this->formatAndSaveCache('last_devices', $data, 'name');
    }

    public function getLastOs()
    {
        return (($val = $this->loadFromCache('last_os')) !== NULL ? $val : $this->saveLastOs());
    }

    public function saveLastOs()
    {
        $data = $this->db->table('onlinedata_form')
            ->select('name, SUM(value) AS c')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->where('type', 'os')
            ->group('name')
            ->order('c DESC')
            ->limit(10)
            ->fetchAll();

        return $this->formatAndSaveCache('last_os', $data, 'name');
    }

    public function getLastBrowser()
    {
        return (($val = $this->loadFromCache('last_browser')) !== NULL ? $val : $this->saveLastBrowser());
    }

    public function saveLastBrowser()
    {
        $data = $this->db->table('onlinedata_form')
            ->select('name, SUM(value) AS c')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->where('type', 'browser')
            ->group('name')
            ->order('c DESC')
            ->limit(10)
            ->fetchAll();

        return $this->formatAndSaveCache('last_browser', $data, 'name');
    }

    public function getLastLang()
    {
        return (($val = $this->loadFromCache('last_lang')) !== NULL ? $val : $this->saveLastLang());
    }

    public function saveLastLang()
    {
        $data = $this->db->table('onlinedata_form')
            ->select('name, SUM(value) AS c')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->where('type', 'lang')
            ->group('name')
            ->order('c DESC')
            ->limit(10)
            ->fetchAll();

        return $this->formatAndSaveCache('last_lang', $data, 'name');
    }

    public function getLastUserLocation()
    {
        return (($val = $this->loadFromCache('last_user_location')) !== NULL ? $val : $this->saveLastUserLocation());
    }

    public function saveLastUserLocation()
    {
        $data = $this->db->table('onlinedata_locations')
            ->select('location, SUM(value) AS c')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->group('location')
            ->order('c DESC')
            ->fetchAll();

        return $this->formatAndSaveCache('last_user_location', $data, 'location');
    }

    public function getLastNewUsers()
    {
        return (($val = $this->loadFromCache('last_new_users')) !== NULL ? $val : $this->saveLastNewUsers());
    }

    public function saveLastNewUsers()
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(new_users) AS c')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(60)
            ->fetchAll();

        return $this->formatAndSaveCache('last_new_users', $data, 'day');
    }

    public function getLastReturnUsers()
    {
        return (($val = $this->loadFromCache('last_return_users')) !== NULL ? $val : $this->saveLastReturnUsers());
    }

    public function saveLastReturnUsers()
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(returning_users) AS c')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(60)
            ->fetchAll();

        return $this->formatAndSaveCache('last_return_users', $data, 'day');
    }

    public function getLastUniqUsers()
    {
        return (($val = $this->loadFromCache('last_uniq_users')) !== NULL ? $val : $this->saveLastUniqUsers());
    }

    public function saveLastUniqUsers()
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(new_users) + SUM(returning_users) AS c')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(60)
            ->fetchAll();

        return $this->formatAndSaveCache('last_uniq_users', $data, 'day');
    }

    public function getLastDwell()
    {
        return (($val = $this->loadFromCache('last_dwell')) !== NULL ? $val : $this->saveLastDwell());
    }

    public function saveLastDwell()
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(dwell_5m) AS c1, SUM(dwell_10m) AS c2, SUM(dwell_30m) AS c3, SUM(dwell_60m) AS c4, SUM(dwell_long) AS c5')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(60)
            ->fetchAll();

        $save = [
            'date' => date('Y-m-d') . ' 08:00:00',
            'names' => [],
            'val1' => [],
            'val2' => [],
            'val3' => [],
            'val4' => [],
            'val5' => [],
        ];

        foreach ($data as $d) {
            $save['names'][] = $d->day;
            $save['val1'][] = $d['c1'];
            $save['val2'][] = $d['c2'];
            $save['val3'][] = $d['c3'];
            $save['val4'][] = $d['c4'];
            $save['val5'][] = $d['c5'];
        }

        $this->cache->remove('last_dwell');
        $this->cache->save('last_dwell', $save);

        return $save;
    }

    public function getLastMaxAverage()
    {
        return (($val = $this->loadFromCache('last_max_average')) !== NULL ? $val : $this->saveLastMaxAverage());
    }

    public function saveLastMaxAverage()
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(average_online) AS c1, SUM(max_online) AS c2')
            ->where('data_date BETWEEN CURRENT_DATE() - INTERVAL 60 DAY AND CURRENT_DATE()')
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(60)
            ->fetchAll();

        $save = [
            'date' => date('Y-m-d') . ' 08:00:00',
            'names' => [],
            'val1' => [],
            'val2' => [],
        ];

        foreach ($data as $d) {
            $save['names'][] = $d->day;
            $save['val1'][] = $d['c1'];
            $save['val2'][] = $d['c2'];
        }

        $this->cache->remove('last_max_average');
        $this->cache->save('last_max_average', $save);

        return $save;
    }
}