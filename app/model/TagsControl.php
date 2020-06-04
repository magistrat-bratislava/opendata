<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;

class TagsControl
{
    use Nette\SmartObject;

    private $db;

    public function __construct(Context $database)
    {
        $this->db = $database;
    }

    public function getAll()
    {
        return $this->db->table('tags')->fetchAll();
    }

    public function getCount()
    {
        return $this->db->table('tags')->count();
    }

    public function get($id)
    {
        $authors = $this->db->table('tags')->get($id);

        if (!$authors)
            throw new \Exception('Značka neexistuje.');

        return $authors;
    }

    public function create($name_sk, $name_en)
    {
        return $this->db->table('tags')->insert([
            'name_sk' => $name_sk,
            'name_en' => $name_en
        ]);
    }

    public function edit($id, $name_sk, $name_en)
    {
        $authors = $this->get($id);
        $authors->update([
            'name_sk' => $name_sk,
            'name_en' => $name_en
        ]);
    }

    public function delete($id)
    {
        $authors = $this->get($id);

        if ($this->db->table('dataset_tags')->where('tags = ?', $id)->count() > 0)
            throw new \Exception('Nemôžete vymazať značku, je použitá v datasetoch');

        $authors->delete();
    }

    public function exists($id)
    {
        $author = $this->db->table('tags')->get($id);

        if (!$author)
            return false;

        return true;
    }
}