<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Utils\Random;
use Nette\Utils\FileSystem;

class DatasetControl
{
    use Nette\SmartObject;

    private $db;
    private $category;
    private $authors;
    private $tag;

    public function __construct(Context $database, CategoryControl $categoryControl, AuthorsControl $authorsControl, TagsControl $tagsControl)
    {
        $this->db = $database;
        $this->category = $categoryControl;
        $this->authors = $authorsControl;
        $this->tag = $tagsControl;
    }

    public function getAll()
    {
        $c = $this->db->table('dataset')->select("dataset.*, authors.name aname, users.name uname, category.name cname")->fetchAll();

        $x = [];

        foreach ($c as $d) {
            $x[] = $d->toArray();
        }

        return $x;
    }

    public function getCount($hidden = false)
    {
        if ($hidden)
            return $this->db->table('dataset')->where('hidden = 0')->count();
        return $this->db->table('dataset')->count();
    }

    public function getFileCount($hidden = 0)
    {
        return $this->db->table('dataset_files')->where('hidden', $hidden)->count();
    }

    public function getLatest()
    {
        return $this->db->table('dataset')->order('created_at DESC')->limit(15)->fetchAll();
    }

    public function getLatestFiles()
    {
        return $this->db->table('dataset_files')->select('dataset_files.*, users.name uname')->order('created_at DESC')->limit(10)->fetchAll();
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
        $dataset = $this->db->table('dataset')->select('dataset.*,authors.name aname,users.name uname')->where('slug', $slug)->fetch();

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
        $search = '*'.$search.'*';

        return count($this->db->query('
            select d.*,u.name uname,c.name cname,
                (SELECT GROUP_CONCAT(df.file_type SEPARATOR \',\')
                FROM dataset_files as df
                WHERE df.dataset = d.id) as files
                from dataset as d
            left join users as u
                on u.id = d.users
            
            left join authors as a
                on a.id = d.authors
            
            left join category as c
                on c.id = d.category
            
            left join (
                select dt.dataset,t.name from dataset_tags as dt
                left join tags as t
                    on dt.tags = t.id
            ) as t
                on t.dataset = d.id
            
            where ( match (d.name,d.slug,d.description,d.licence) against (? IN BOOLEAN MODE)
            or match (u.name) against (? IN BOOLEAN MODE)
            or match (a.name) against (? IN BOOLEAN MODE)
            or match (c.name) against (? IN BOOLEAN MODE)
            or match (t.name) against (? IN BOOLEAN MODE) )
                and d.hidden = 0

            group by d.id
            order by d.id desc
        ', $search, $search, $search, $search, $search)->fetchAll());
    }

    public function search($search, $page)
    {
        $search = '*'.$search.'*';

        return $this->db->query('
            select d.*,a.name aname,c.name cname,
                (SELECT GROUP_CONCAT(df.file_type SEPARATOR \',\')
                FROM dataset_files as df
                WHERE df.dataset = d.id) as files
                from dataset as d
            left join users as u
                on u.id = d.users
            
            left join authors as a
                on a.id = d.authors
            
            left join category as c
                on c.id = d.category
            
            left join (
                select dt.dataset,t.name from dataset_tags as dt
                left join tags as t
                    on dt.tags = t.id
            ) as t
                on t.dataset = d.id
            
            where ( match (d.name,d.slug,d.description,d.licence) against (? IN BOOLEAN MODE)
            or match (u.name) against (? IN BOOLEAN MODE)
            or match (a.name) against (? IN BOOLEAN MODE)
            or match (c.name) against (? IN BOOLEAN MODE)
            or match (t.name) against (? IN BOOLEAN MODE) )
                and d.hidden = 0

            group by d.id
            order by d.id desc
            limit ?,10
        ', $search, $search, $search, $search, $search, intval($page)*10)->fetchAll();
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

    public function create($name, $slug, $description, $authors, $licence, $category, $tags, $users)
    {
        //TODO: vygenerovat uniq id

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

        $row = $this->db->table('dataset')->insert([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'authors' => $authors,
            'licence' => $licence,
            'category' => $category,
            'users' => $users,
            'uniq_id' => $uniq,
            'downloaded' => 0
        ]);

        if (!$row)
            return null;

        $this->setTags($row->id, $tags);

        return $row;
    }

    public function edit($id, $name, $slug, $description, $authors, $licence, $category, $tags)
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

        $dataset->update([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'authors' => $authors,
            'licence' => $licence,
            'category' => $category,
        ]);

        $this->setTags($id, $tags);
    }

    public function delete($id)
    {
        $dataset = $this->get($id);

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
    }

    public function hide($id)
    {
        $dataset = $this->get($id);

        $hidden = 1;

        if ($dataset->hidden)
            $hidden = 0;

        $dataset->update([
            'hidden' => $hidden
        ]);
    }

    public function getTags($dataset)
    {
        return $this->db->table('dataset_tags')->where('dataset', $dataset)->fetchAll();
    }

    public function getTagsName($dataset)
    {
        return $this->db->table('dataset_tags')->select('tags.name')->where('dataset', $dataset)->fetchAll();
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
}
