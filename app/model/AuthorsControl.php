<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;

class AuthorsControl
{
    use Nette\SmartObject;

    private $db;

    public function __construct(Context $database)
    {
        $this->db = $database;
    }

    public function getAll()
    {
        return $this->db->table('authors')->fetchAll();
    }

    public function getCount()
    {
        return $this->db->table('authors')->count();
    }

    public function get($id)
    {
        $authors = $this->db->table('authors')->get($id);

        if (!$authors)
            throw new \Exception('Autor neexistuje');

        return $authors;
    }

    public function create($name_sk, $name_en)
    {
        return $this->db->table('authors')->insert([
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

        if ($this->db->table('dataset')->where('authors = ?', $id)->count() > 0)
            throw new \Exception('Nemôžete vymazať autora, je použitý v datasetoch.');

        $authors->delete();
    }

    public function exists($id)
    {
        $author = $this->db->table('authors')->get($id);

        if (!$author)
            return false;

        return true;
    }
}