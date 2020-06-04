<?php

namespace App\Model;

use Nette;
use Nette\Caching\Cache;

/*

INSERT INTO `category` (`id`, `name`, `slug`) VALUES (NULL, 'Doprava', 'doprava'), (NULL, 'Ekonomika a práca', 'ekonomika'), (NULL, 'Infraštruktúra, výstavba a bývanie', 'infrastruktura'), (NULL, 'Kultúra, voľný čas, šport a cestovný ruch', 'kultura'), (NULL, 'Obyvateľstvo', 'obyvatelstvo'), (NULL, 'Politika a voľby', 'politika'), (NULL, 'Priestorové údaje', 'priestorove-udaje'), (NULL, 'Rozpočet a dane', 'rozpocet-a-dane'), (NULL, 'Sociálna oblasť', 'socialna-oblast'), (NULL, 'Veda a vzdelávanie', 'veda'), (NULL, 'Zákony a spravodlivosť', 'zakony'), (NULL, 'Zdravie, životné prostredie a klíma', 'zdravie');

*/

class CategoryControl
{
    use Nette\SmartObject;

    private $categories;
    private $id;
    private $db;

    public function __construct(Nette\Database\Context $db)
    {
        $this->db = $db;
    }

    public function getAll($hidden = false, $locale = 'sk')
    {
        if ($hidden)
            return $this->db->query('
                select c.*, (select count(d.id) from dataset as d where d.category = c.id and d.hidden = 0) as dcount
                    from category as c
                order by c.name_'.$locale.' asc
            ');
        return $this->db->query('
            select c.*, (select count(d.id) from dataset as d where d.category = c.id) as dcount
                from category as c
            order by c.name_'.$locale.' asc
        ');
    }

    public function getCount()
    {
        return $this->db->table('category')->order('name ASC')->count();
    }

    public function getDatasetCount()
    {
        $dataset = $this->getAll();

        $dataset_count = [];

        foreach ($dataset as $d) {
            $dataset_count[] = [
                'id' => $d->id,
                'name' => $d->name_sk,
                'slug' => $d->slug,
                'count' => $this->db->table('dataset')->where('category', $d->id)->count()
            ];
        }

        return $dataset_count;
    }

    public function get($id)
    {
        $category = $this->db->table('category')->get($id);

        if (!$category)
            throw new \Exception('Kategória neexistuje.');

        return $category;
    }

    public function getSlug($slug)
    {
        $category = $this->db->table('category')->where('slug', $slug)->fetch();

        if (!$category)
            throw new \Exception('Kategória neexistuje.');

        return $category;
    }

    public function exists($id)
    {
        if (!$this->db->table('category')->get($id))
            return false;

        return true;
    }

    public function existSlug($slug)
    {
        if (!$this->db->table('category')->where('slug', $slug)->fetch())
            return false;

        return true;
    }

    public function create($name_sk, $name_en, $slug)
    {
        if ($this->existSlug($slug))
            throw new \Exception('URL názov už existuje.');

        $slug = iconv("utf-8", "us-ascii//TRANSLIT", $slug);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace("/[^a-zA-Z\-]/i", "", $slug);

        if (empty($slug))
            throw new \Exception('URL názov nesmie obsahovať žiadne špeciálne znaky okrem pomlčky.');

        return $this->db->table('category')->insert([
            'name_sk' => $name_sk,
            'name_en' => $name_en,
            'slug' => $slug
        ]);
    }

    public function edit($id, $name_sk, $name_en, $slug)
    {
        if ($this->db->table('category')->where('slug', $slug)->where('id != ?', $id)->fetch())
            throw new \Exception('URL názov už existuje.');

        $slug = iconv("utf-8", "us-ascii//TRANSLIT", $slug);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace("/[^a-zA-Z\-]/i", "", $slug);

        if (empty($slug))
            throw new \Exception('URL názov nesmie obsahovať žiadne špeciálne znaky okrem pomlčky.');

        $category = $this->get($id);
        $category->update([
            'name_sk' => $name_sk,
            'name_en' => $name_en,
            'slug' => $slug
        ]);
    }

    public function delete($id)
    {
        $category = $this->get($id);

        if ($this->db->table('dataset')->where('category = ?', $id)->count() > 0)
            throw new \Exception('Nemôžeš vymazať kategóriu, kategória je použitá v datasetoch.');

        $category->delete();
    }

    public function loadCategories()
    {
        $storage = new Nette\Caching\Storages\FileStorage('/tmp');
        $cache = new Cache($storage);

        if (($val = $cache->load('categories')) !== NULL)
            return $val;

        $cat = $this->db->table('category')->order('id')->fetchAll();

        if (!$cat)
            return NULL;

        $categories = [];

        foreach ($cat as $c) {
            $categories['name_sk'][$c->id] = $c->name_sk;
            $categories['name_en'][$c->id] = $c->name_en;
        }

        $cache->remove('categories');
        $cache->save('categories', $categories);

        return $cat;
    }
}