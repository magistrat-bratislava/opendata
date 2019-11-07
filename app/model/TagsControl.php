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

    public function create($name)
    {
        return $this->db->table('tags')->insert([
            'name' => $name
        ]);
    }

    public function edit($id, $name)
    {
        $authors = $this->get($id);
        $authors->update([
            'name' => $name,
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