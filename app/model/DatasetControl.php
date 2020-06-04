<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Utils\Random;
use Nette\Utils\FileSystem;
use Nette\Caching\Cache;
use Contributte\Translation\LocalesResolvers\Router;

class DatasetControl
{
    use Nette\SmartObject;

    /** @persistent */
    public $locale;

    private $db;
    private $category;
    private $authors;
    private $tag;
    private $translator;

    public function __construct(Context $database, CategoryControl $categoryControl, AuthorsControl $authorsControl, TagsControl $tagsControl, Router $router)
    {
        $this->db = $database;
        $this->category = $categoryControl;
        $this->authors = $authorsControl;
        $this->tag = $tagsControl;
        $this->translator = $router;
    }

    public function getAll()
    {
        return $this->db->table('dataset')->fetchAll();
    }

    public function getByUser($id)
    {
        return $this->db->table('dataset')->where('users', $id)->fetchAll();
    }

    public function getCount($hidden = false, $users = 0)
    {
        $count = $this->db->table('dataset');

        if ($hidden)
            $count->where('hidden = 0');

        if ($users != 0)
            $count->where('users', $users);

        return $count->count();
    }

    public function getFileCount($hidden = 0, $users = 0)
    {
        if ($users == 0)
            return $this->db->table('dataset_files')->where('hidden', $hidden)->count();
        return $this->db->table('dataset_files')->where('hidden', $hidden)->where('users', $users)->count();
    }

    public function getLatest($users = 0)
    {
        if ($users == 0)
            return $this->db->table('dataset')->order('created_at DESC')->limit(15)->fetchAll();
        return $this->db->table('dataset')->where('users', $users)->order('created_at DESC')->limit(15)->fetchAll();
    }

    public function getLatestFiles($users = 0)
    {
        if ($users == 0)
            return $this->db->table('dataset_files')->select('dataset_files.*, users.name uname')->order('created_at DESC')->limit(10)->fetchAll();
        return $this->db->table('dataset_files')->select('dataset_files.*, users.name uname')->where('dataset.users', $users)->order('created_at DESC')->limit(10)->fetchAll();
    }

    public function get($id)
    {
        $dataset = $this->db->table('dataset')->get($id);

        if (!$dataset)
            throw new \Exception('Dataset doesn\'t exists');

        return $dataset;
    }

    public function getTables($slug)
    {
        $dataset = $this->db->table('dataset')->where('slug', $slug)->fetch();

        if (!$dataset)
            throw new \Exception('Dataset neexistuje.');

        return $dataset;
    }

    public function getByCategory($id)
    {
        return $this->db->table('dataset')->select('dataset.*, users.name uname')->where('category', $id)->where('hidden','0')->fetchAll();
    }

    public function search_count($search)
    {
        $search = '*'.trim($search).'*';

        return $this->db->table('dataset')
            ->where('MATCH (dataset.name_sk,dataset.name_en,dataset.slug,dataset.description_sk,dataset.description_en,dataset.licence) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (authors.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (authors.name_en) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (category.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (category.name_en) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (:dataset_tags.tags.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (:dataset_tags.tags.name_en) AGAINST (? IN BOOLEAN MODE)', $search, $search, $search, $search, $search, $search, $search)
            ->where('hidden = 0')
            ->group('id')
            ->order('id DESC')
            ->count();
    }

    public function search($search, $page)
    {
        $search = '*'.trim($search).'*';

        return $this->db->table('dataset')
            ->where('MATCH (dataset.name_sk,dataset.name_en,dataset.slug,dataset.description_sk,dataset.description_en,dataset.licence) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (authors.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (authors.name_en) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (category.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (category.name_en) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (:dataset_tags.tags.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (:dataset_tags.tags.name_en) AGAINST (? IN BOOLEAN MODE)', $search, $search, $search, $search, $search, $search, $search)
            ->where('hidden = 0')
            ->group('id')
            ->order('id DESC')
            ->limit(10, intval($page)*10)
            ->fetchAll();
    }

    public function searchConditions($val)
    {
        $datasets = $this->db->table('dataset');
        $datasets->where('dataset.hidden = 0');

        if (!empty($val->search)) {
            $search = '*'.trim($val->search).'*';

            $datasets->where('MATCH (dataset.name_sk,dataset.name_en,dataset.slug,dataset.description_sk,dataset.description_en,dataset.licence) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (authors.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (authors.name_en) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (category.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (category.name_en) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (:dataset_tags.tags.name_sk) AGAINST (? IN BOOLEAN MODE)
            OR MATCH (:dataset_tags.tags.name_en) AGAINST (? IN BOOLEAN MODE)', $search, $search, $search, $search, $search, $search, $search);
        }

        if ($val->category != 0 && is_numeric($val->category)) {
            $cat = $this->db->table('category')->get($val->category);

            if ($cat)
                $datasets->where('category', $val->category);
        }

        if ($val->year != 0)
            $datasets->where('year', $val->year);
        if ($val->district != '0')
            $datasets->where('district', $val->district);
        if ($val->authors != 0)
            $datasets->where('authors', $val->authors);
        if (!empty($val->uniq_id))
            $datasets->where('uniq_id', $val->uniq_id);
        if ($val->visualization == 1)
            $datasets->where(':dataset_files.powerbi IS NOT NULL OR dataset.powerbi IS NOT NULL');
        if ($val->visualization == 2)
            $datasets->where('dataset.map IS NOT NULL OR :dataset_files.map IS NOT NULL');

        $datasets->order('id DESC');

        return $datasets;
    }

    public function searchExtend($val, $page)
    {
        $count = $this->searchConditions($val)->count();
        $data = $this->searchConditions($val)->limit(10, intval($page)*10)->fetchAll();

        return ['data' => $data, 'count' => $count];
    }

    public function exists($id)
    {
        $dataset = $this->db->table('dataset')->get($id);

        if (!$dataset)
            return false;

        return true;
    }

    public function existSlug($slug)
    {
        if ($this->db->table('dataset')->where('slug', $slug)->fetch())
            return true;

        return false;
    }

    public function create($name_sk, $name_en, $slug, $description_sk, $description_en, $authors, $licence, $category, $tags, $powerbi, $map, $year, $district, $onlinedata, $users)
    {
        if (!$this->category->exists($category))
            throw new \Exception('Kategória neexistuje.');

        if (!$this->authors->exists($authors))
            throw new \Exception('Autor neexistuje');

        if ($this->existSlug($slug))
            throw new \Exception('URL názov už existuje.');

        foreach ($tags as $t) {
            if (!$this->tag->exists($t))
                throw new \Exception('Jedna/viacero značiek neexistuje.');
        }

        $slug = iconv("utf-8", "us-ascii//TRANSLIT", $slug);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace("/[^a-zA-Z\-]/i", "", $slug);

        if (empty($slug))
            throw new \Exception('URL názov nesmie obsahovať žiadne špeciálne znaky okrem pomlčky.');

        $uniq = Random::generate(20);

        if (empty($powerbi))
            $powerbi = NULL;

        if (empty($map))
            $map = NULL;

        if (empty($year))
            $year = NULL;

        if (empty($district))
            $district = NULL;

        if (!is_numeric($year) && $year != NULL)
            throw new \Exception('Rok musí byť číslo.');

        if ($onlinedata != 0) {
            $exists = $this->db->table('dataset')
                ->where('onlinedata', $onlinedata)
                ->fetch();

            if ($exists)
                throw new \Exception('Dataset s týmto typom online dát už existuje.');
        }

        $row = $this->db->table('dataset')->insert([
            'name_sk' => $name_sk,
            'name_en' => $name_en,
            'slug' => $slug,
            'description_sk' => $description_sk,
            'description_en' => $description_en,
            'authors' => $authors,
            'licence' => $licence,
            'category' => $category,
            'users' => $users,
            'uniq_id' => $uniq,
            'downloaded' => 0,
            'powerbi' => $powerbi,
            'map' => $map,
            'year' => $year,
            'district' => $district,
			'onlinedata' => $onlinedata,			
        ]);

        if (!$row)
            return null;

        $this->setTags($row->id, $tags);
        $this->saveYears();
        $this->saveDistricts();

        $this->loadLast('dataset');
        $this->loadLast('powerbi');
        $this->loadLast('map');

        return $row;
    }

    public function edit($id, $name_sk, $name_en, $slug, $description_sk, $description_en, $authors, $licence, $category, $tags, $powerbi, $map, $year, $district, $onlinedata)
    {
        $dataset = $this->get($id);

        if (!$this->category->exists($category))
            throw new \Exception('Kategória neexistuje.');

        if (!$this->authors->exists($authors))
            throw new \Exception('Autor neexistuje');

        if ($this->db->table('dataset')->where('slug', $slug)->where('id != ?', $id)->fetch())
            throw new \Exception('URL názov už existuje.');

        foreach ($tags as $t) {
            if (!$this->tag->exists($t))
                throw new \Exception('Jedna/viacero značiek neexistuje.');
        }

        $slug = iconv("utf-8", "us-ascii//TRANSLIT", $slug);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace("/[^a-zA-Z\-]/i", "", $slug);

        if (empty($slug))
            throw new \Exception('URL názov nesmie obsahovať žiadne špeciálne znaky okrem pomlčky.');

        if (empty($powerbi))
            $powerbi = NULL;

        if (empty($map))
            $map = NULL;

        if (empty($year))
            $year = NULL;

        if (empty($district))
            $district = NULL;

        if (!is_numeric($year) && $year != NULL)
            throw new \Exception('Rok musí byť číslo.');

        if ($onlinedata != 0) {
            $exists = $this->db->table('dataset')
                ->where('onlinedata', $onlinedata)
                ->fetch();

            if ($exists && $dataset->id != $exists->id)
                throw new \Exception('Dataset s týmto typom online dát už existuje.');
        }

        $dataset->update([
            'name_sk' => $name_sk,
            'name_en' => $name_en,
            'slug' => $slug,
            'description_sk' => $description_sk,
            'description_en' => $description_en,
            'authors' => $authors,
            'licence' => $licence,
            'category' => $category,
            'powerbi' => $powerbi,
            'map' => $map,
            'year' => $year,
            'district' => $district,
			'onlinedata' => $onlinedata,			
            'changed_at' => new \DateTime()
        ]);

        $this->setTags($id, $tags);
        $this->saveYears();
        $this->saveDistricts();

        $this->loadLast('dataset');
        $this->loadLast('powerbi');
        $this->loadLast('map');
    }

    public function delete($id)
    {
        $dataset = $this->get($id);

        $this->db->table('dataset_tags')->where('dataset', $id)->delete();

        $files = $this->db->table('dataset_files')->select('dataset_files.*, users.name uname')->where('dataset', $dataset)->fetchAll();

        if (count($files) > 0) {
            foreach ($files as $f) {
                $file = $this->db->table('dataset_files')->get($f->id);
                FileSystem::delete(__DIR__ . '/../../uploads/dataset_file_' . $file->id . '.txt');
                $file->delete();
            }
        }

        $dataset->delete();
        $this->deleteTags($id);

        $this->loadLast('dataset');
        $this->loadLast('powerbi');
        $this->loadLast('map');
    }

    public function hide($id)
    {
        $dataset = $this->get($id);

        $hidden = 1;

        if ($dataset->hidden)
            $hidden = 0;

        $dataset->update([
            'hidden' => $hidden,
            'changed_at' => new \DateTime()
        ]);

        $this->loadLast('dataset');
        $this->loadLast('powerbi');
        $this->loadLast('map');
    }

    public function getTags($dataset)
    {
        return $this->db->table('dataset_tags')->where('dataset', $dataset)->fetchAll();
    }

    public function getTagsName($dataset)
    {
        return $this->db->table('dataset_tags')->select('tags.*')->where('dataset', $dataset)->fetchAll();
    }

    public function setTags($dataset, $tags)
    {
        if (!is_array($tags))
            $tags = [$tags];

        $this->deleteTags($dataset);

        foreach ($tags as $t) {
            $this->db->table('dataset_tags')->insert([
                'dataset' => $dataset,
                'tags' => $t,
            ]);
        }
    }

    public function deleteTags($dataset)
    {
        $this->db->query('delete from dataset_tags where dataset = ?', $dataset);
    }

    public function getLast($type = 'dataset')
    {
        if (!in_array($type, ['dataset', 'powerbi', 'map']))
            return NULL;

        $storage = new Nette\Caching\Storages\FileStorage('/tmp');
        $cache = new Cache($storage);

        if (($val = $cache->load('last_'.$type)) !== NULL)
            return $val;

        return $this->loadLast($type);
    }

    public function loadLast($type)
    {
        if (!in_array($type, ['dataset', 'powerbi', 'map']))
            return NULL;

        $storage = new Nette\Caching\Storages\FileStorage('/tmp');
        $cache = new Cache($storage);
        $data = NULL;

        $data = $this->db->table('dataset')
            ->select('dataset.name_sk, dataset.name_en, dataset.slug, dataset.changed_at')
            ->where('dataset.hidden = 0');

        switch ($type) {
            case 'dataset':
                $data->where(':dataset_files.powerbi IS NULL AND dataset.powerbi IS NULL')
                    ->where(':dataset_files.map IS NULL AND dataset.map IS NULL');
                break;

            case 'powerbi':
                $data->where(':dataset_files.powerbi IS NOT NULL OR dataset.powerbi IS NOT NULL')
                    ->where(':dataset_files.map IS NULL AND dataset.map IS NULL');
                break;

            case 'map':
                $data->where(':dataset_files.map IS NOT NULL OR dataset.map IS NOT NULL')
                    ->where(':dataset_files.powerbi IS NULL AND dataset.powerbi IS NULL');
                break;
        }

        $data->order('dataset.changed_at DESC')
            ->limit(40);

        $datasets = [];

        foreach ($data as $d) {
            $datasets[] = [
                'name_sk' => $d->name_sk,
                'name_en' => $d->name_en,
                'slug' => $d->slug,
                'date' => date('d. m. Y', strtotime($d->changed_at)),
            ];
        }

        $cache->remove('last_'.$type);
        $cache->save('last_'.$type, $datasets);

        return $datasets;
    }

    public function loadYears()
    {
        $storage = new Nette\Caching\Storages\FileStorage('/tmp');
        $cache = new Cache($storage);

        if (($val = $cache->load('years')) !== NULL)
            return $val;

        return $this->saveYears();
    }

    public function saveYears()
    {
        $cat = $this->db->table('dataset')
            ->select('year AS years')
            ->group('year')
            ->having('years IS NOT NULL')
            ->order('years DESC')
            ->fetchAll();

        if (!$cat)
            return [];

        $y = [];

        foreach ($cat as $c)
            $y[] = $c->years;

        $storage = new Nette\Caching\Storages\FileStorage('/tmp');
        $cache = new Cache($storage);

        $cache->remove('years');
        $cache->save('years', $y);

        return $y;
    }

    public function loadDistricts()
    {
        $storage = new Nette\Caching\Storages\FileStorage('/tmp');
        $cache = new Cache($storage);

        if (($val = $cache->load('districts')) !== NULL)
            return $val;

        return $this->saveDistricts();
    }

    public function saveDistricts()
    {
        $cat = $this->db->table('dataset')
            ->select('district AS districts')
            ->having('districts IS NOT NULL')
            ->group('district')
            ->order('districts')
            ->fetchAll();

        if (!$cat)
            return [];

        $y = [];

        foreach ($cat as $c)
            $y[] = $c->districts;

        $storage = new Nette\Caching\Storages\FileStorage('/tmp');
        $cache = new Cache($storage);

        $cache->remove('districts');
        $cache->save('districts', $y);

        return $y;
    }
}
