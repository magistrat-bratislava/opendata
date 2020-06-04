<?php

namespace App\Model;

use Nette;
use Nette\Caching\Cache;
use Nette\Database\Context;

class OnlinedataControl
{
    use Nette\SmartObject;

    private $db;
    private $req;
    private $cache;
    private $onlinedata;

    public function __construct(Context $database, Nette\Http\Response $req)
    {
        $this->db = $database;
        $this->req = $req;
        $this->onlinedata = [
            '1' => 'form',
            '2' => 'locations',
            '3' => 'summary',
        ];

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

    public function loadMonths($onlinedata)
    {
        return (($val = $this->loadFromCache('onlinedata_months_'.$this->onlinedata[$onlinedata])) !== NULL ? $val : $this->saveMonths($onlinedata));
    }

    public function saveMonths($onlinedata)
    {
        $data = $this->db->table('onlinedata_'.$this->onlinedata[$onlinedata])
            ->select('DATE_FORMAT(data_date,?) AS slug', '%Y-%m')
            ->group('slug')
            ->order('slug DESC');

        $months = ['date' => date('Y-m-d') . ' 08:00:00', 'data' => []];

        foreach ($data as $d) {
            $months['data'][] = [
                'slug' => $d->slug,
                'name' => str_replace('-', '/', $d->slug),
            ];
        }

        $this->cache->remove('onlinedata_months_'.$this->onlinedata[$onlinedata]);
        $this->cache->save('onlinedata_months_'.$this->onlinedata[$onlinedata], $months);

        return $months;
    }

    public function download($slug, $onlinedata)
    {
        $dataset = $this->db->table('dataset')
            ->where('onlinedata', $onlinedata)
            ->limit(1)
            ->fetch();

        if (!$dataset)
            return false;

        $dataset->update([
            'downloaded' => $dataset->downloaded + 1
        ]);

        $date = explode('-', $slug);

        $data = $this->db->table('onlinedata_'.$this->onlinedata[$onlinedata])
            ->where('YEAR(data_date)', $date[0])
            ->where('MONTH(data_date)', $date[1])
            ->order('data_date, id');

        switch ($onlinedata) {
            case 1:
                $content = '"date";"type";"name";"value"';
                break;
            case 2:
                $content = '"date";"location";"value"';
                break;
            case 3:
                $content = '"date";"new_users";"returning_users";"average_online";"max_online";"dwell_5m";"dwell_10m";"dwell_30m";"dwell_60m";"dwell_long"';
                break;
            default:
                $content = '';
                break;
        }

        $content .= "\n";

        foreach ($data as $d) {
            $r = [];
            foreach ($d as $k => $v) {
                if ($k != 'id')
                    $r[] = $v;
            }
            $content .= implode(';', $r)."\n";
        }

        $this->req->setHeader('Content-Type', 'application/octet-stream');
        $this->req->setHeader('Content-Disposition', 'attachment; filename=onlinedata.'.$this->onlinedata[$onlinedata].'.'.$slug.'.csv');
        $this->req->setHeader('Content-Length', strlen($content));
        echo $content;
        exit;
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

    public function getDevices($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_devices_'.$y.'_'.$m)) !== NULL ? $val : $this->saveDevices($y, $m));
    }

    public function saveDevices($y, $m)
    {
        $data = $this->db->table('onlinedata_form')
            ->select('name, SUM(value) AS c')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->where('type', 'device_type')
            ->group('name')
            ->order('c DESC')
            ->limit(10)
            ->fetchAll();

        return $this->formatAndSaveCache('onlinedata_devices_'.$y.'_'.$m, $data, 'name');
    }

    public function getOs($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_os_'.$y.'_'.$m)) !== NULL ? $val : $this->saveOs($y, $m));
    }

    public function saveOs($y, $m)
    {
        $data = $this->db->table('onlinedata_form')
            ->select('name, SUM(value) AS c')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->where('type', 'os')
            ->group('name')
            ->order('c DESC')
            ->limit(10)
            ->fetchAll();

        return $this->formatAndSaveCache('onlinedata_os_'.$y.'_'.$m, $data, 'name');
    }

    public function getBrowser($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_browser_'.$y.'_'.$m)) !== NULL ? $val : $this->saveBrowser($y, $m));
    }

    public function saveBrowser($y, $m)
    {
        $data = $this->db->table('onlinedata_form')
            ->select('name, SUM(value) AS c')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->where('type', 'browser')
            ->group('name')
            ->order('c DESC')
            ->limit(10)
            ->fetchAll();

        return $this->formatAndSaveCache('onlinedata_browser_'.$y.'_'.$m, $data, 'name');
    }

    public function getLang($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_lang_'.$y.'_'.$m)) !== NULL ? $val : $this->saveLang($y, $m));
    }

    public function saveLang($y, $m)
    {
        $data = $this->db->table('onlinedata_form')
            ->select('name, SUM(value) AS c')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->where('type', 'lang')
            ->group('name')
            ->order('c DESC')
            ->limit(10)
            ->fetchAll();

        return $this->formatAndSaveCache('onlinedata_lang_'.$y.'_'.$m, $data, 'name');
    }

    public function getUserLocation($y, $m)
    {
        return $this->saveUserLocation($y, $m);
        return (($val = $this->loadFromCache('onlinedata_user_location_'.$y.'_'.$m)) !== NULL ? $val : $this->saveUserLocation($y, $m));
    }

    public function saveUserLocation($y, $m)
    {
        $data = $this->db->table('onlinedata_locations')
            ->select('location, SUM(value) AS c')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->group('location')
            ->order('c DESC')
            ->fetchAll();

        return $this->formatAndSaveCache('onlinedata_user_location_'.$y.'_'.$m, $data, 'location');
    }

    public function getAllLocations()
    {
        return (($val = $this->loadFromCache('onlinedata_all_location')) !== NULL ? $val : $this->saveAllLocation());
    }

    public function saveAllLocation()
    {
        $data = $this->db->table('onlinedata_locations')
            ->select('location')
            ->group('location')
            ->order('location')
            ->fetchAll();

        $locations = [];

        foreach ($data as $d) {
            if ($d->location == 'Ostatné')
                continue;

            $locations[] = $d->location;
        }

        $this->cache->remove('onlinedata_all_location');
        $this->cache->save('onlinedata_all_location', $locations);

        return $locations;
    }


    public function getNewUsers($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_new_users_'.$y.'_'.$m)) !== NULL ? $val : $this->saveNewUsers($y, $m));
    }

    public function saveNewUsers($y, $m)
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(new_users) AS c')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(31)
            ->fetchAll();

        return $this->formatAndSaveCache('onlinedata_new_users_'.$y.'_'.$m, $data, 'day');
    }

    public function getReturnUsers($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_return_users_'.$y.'_'.$m)) !== NULL ? $val : $this->saveReturnUsers($y, $m));
    }

    public function saveReturnUsers($y, $m)
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(returning_users) AS c')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(31)
            ->fetchAll();

        return $this->formatAndSaveCache('onlinedata_return_users_'.$y.'_'.$m, $data, 'day');
    }

    public function getUniqUsers($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_uniq_users_'.$y.'_'.$m)) !== NULL ? $val : $this->saveUniqUsers($y, $m));
    }

    public function saveUniqUsers($y, $m)
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(new_users) + SUM(returning_users) AS c')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(31)
            ->fetchAll();

        return $this->formatAndSaveCache('onlinedata_uniq_users_'.$y.'_'.$m, $data, 'day');
    }

    public function getDwell($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_dwell_'.$y.'_'.$m)) !== NULL ? $val : $this->saveDwell($y, $m));
    }

    public function saveDwell($y, $m)
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(dwell_5m) AS c1, SUM(dwell_10m) AS c2, SUM(dwell_30m) AS c3, SUM(dwell_60m) AS c4, SUM(dwell_long) AS c5')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(31)
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

        $this->cache->remove('onlinedata_dwell_'.$y.'_'.$m);
        $this->cache->save('onlinedata_dwell_'.$y.'_'.$m, $save);

        return $save;
    }

    public function getMaxAverage($y, $m)
    {
        return (($val = $this->loadFromCache('onlinedata_max_average_'.$y.'_'.$m)) !== NULL ? $val : $this->saveMaxAverage($y, $m));
    }

    public function saveMaxAverage($y, $m)
    {
        $data = $this->db->table('onlinedata_summary')
            ->select('DATE(data_date) AS day, SUM(average_online) AS c1, SUM(max_online) AS c2')
            ->where('YEAR(data_date)', $y)
            ->where('MONTH(data_date)', $m)
            ->group('DATE(data_date)')
            ->order('day')
            ->limit(31)
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

        $this->cache->remove('onlinedata_max_average_'.$y.'_'.$m);
        $this->cache->save('onlinedata_max_average_'.$y.'_'.$m, $save);

        return $save;
    }
}
