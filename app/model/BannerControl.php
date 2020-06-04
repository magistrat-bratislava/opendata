<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;

class BannerControl
{
    use Nette\SmartObject;

    private $db;

    public function __construct(Context $database)
    {
        $this->db = $database;
    }

    public function getAll()
    {
        return $this->db->table('banner_stats')->order('day DESC')->limit(30)->fetchAll();
    }

    public function getLastDays($days)
    {
        return $this->db->table('banner_stats')->where('day >= DATE(NOW()) - INTERVAL ? DAY', $days)->fetchAll();
    }

    public function count()
    {
        $day = date('Y-m-d', time());

        $actual = $this->db->table('banner_stats')->where('day', $day)->fetch();

        if ($actual) {
            $actual->update(['count' => $actual->count + 1]);
        } else {
            $this->db->table('banner_stats')->insert(['day' => $day, 'count' => 1]);
        }
    }
}