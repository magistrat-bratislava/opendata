<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Utils\FileSystem;

class FileControl
{
    use Nette\SmartObject;

    private $db;
    private $dataset;
    private $category;
    private $authors;
    private $tag;
    private $req;
    public $accepted_file_types = ['csv', 'txt'];

    public function __construct(Context $database, DatasetControl $datasetControl, CategoryControl $categoryControl, AuthorsControl $authorsControl, TagsControl $tagsControl, Nette\Http\Response $req)
    {
        $this->db = $database;
        $this->dataset = $datasetControl;
        $this->category = $categoryControl;
        $this->authors = $authorsControl;
        $this->tag = $tagsControl;
        $this->req = $req;
    }

    public function getByDataset($dataset, $hidden = false)
    {
        if (!$this->dataset->exists($dataset))
            return null;

        if ($hidden)
            return $this->db->table('dataset_files')->select('dataset_files.*, users.name uname')->where('dataset', $dataset)->where('hidden = 0')->fetchAll();
        return $this->db->table('dataset_files')->select('dataset_files.*, users.name uname')->where('dataset', $dataset)->fetchAll();
    }

    public function get($id)
    {
        $file = $this->db->table('dataset_files')->get($id);

        if (!$file)
            throw new \Exception('Súbor neexistuje');

        return $file;
    }

    public function exists($id)
    {
        $file = $this->db->table('dataset_files')->get($id);

        if (!$file)
            return false;

        return true;
    }

    public function getSlug($dataset, $id)
    {
        $file = $this->db->table('dataset_files')->where('dataset', $dataset)->where('ord', $id)->fetch();

        if (!$file)
            throw new \Exception('Súbor neexistuje');

        return $file;
    }

    public function setFileOrder($dataset)
    {
        $files = $this->db->table('dataset_files')->where('dataset', $dataset)->order('created_at')->fetchAll();

        if (!$files)
            return;

        $i = 1;

        foreach ($files as $f) {
            $f->update([
                'ord' => $i
            ]);

            $i++;
        }
    }

    public function create($name_sk, $name_en, $file, $users, $dataset, $powerbi, $map)
    {
        if (!$file->isOk())
            throw new \Exception('Pri nahrávaní súboru nastal problém.');

        $end = explode('.', $file->getSanitizedName());
        $end = array_pop($end);

        if (empty($powerbi))
            $powerbi = NULL;

        if (empty($map))
            $map = NULL;

        $row = $this->db->table('dataset_files')->insert([
            'name_sk' => $name_sk,
            'name_en' => $name_en,
            'users' => $users,
            'dataset' => $dataset,
            'file_type' => $end,
            'powerbi' => $powerbi,
            'map' => $map,
        ]);

        $this->setFileOrder($dataset);

        try {
            $file->move(__DIR__ . '/../../uploads/dataset_file_' . $row->id . '.txt');
        }
        catch (\Exception $e) {
            $row->delete();
            throw new \Exception('Pri nahrávaní súboru nastal problém.');
        }

        $dataset = $this->dataset->get($dataset);
        $dataset->update([
            'changed_at' => new \DateTime()
        ]);
    }

    public function edit($id, $name_sk, $name_en, $powerbi, $map)
    {
        $file = $this->get($id);

        if (empty($powerbi))
            $powerbi = NULL;

        if (empty($map))
            $map = NULL;

        $file->update([
            'name_sk' => $name_sk,
            'name_en' => $name_en,
            'powerbi' => $powerbi,
            'map' => $map,
        ]);

        $dataset = $this->dataset->get($file->dataset);
        $dataset->update([
            'changed_at' => new \DateTime()
        ]);
    }

    public function delete($id)
    {
        $file = $this->get($id);
        $dataset = $file->dataset;

        FileSystem::delete(__DIR__ . '/../../uploads/dataset_file_' . $file->id . '.txt');
        $file->delete();

        $this->setFileOrder($dataset);
    }

    public function download($id, $ord)
    {
        $file = $this->get($id);
        $dataset = $this->dataset->get($file->dataset);

        if (!file_exists(__DIR__ . '/../../uploads/dataset_file_' . $file->id . '.txt'))
            throw new \Exception('Súbor neexistuje.');

        $content = file_get_contents(__DIR__ . '/../../uploads/dataset_file_' . $file->id . '.txt');

        $this->req->setHeader('Content-Type', 'application/octet-stream');
        $this->req->setHeader('Content-Disposition', 'attachment; filename=dataset.'.$dataset->uniq_id.'.'.$ord.'.'.$file->file_type);
        $this->req->setHeader('Content-Length', strlen($content));
        echo $content;
        exit;
    }

    public function insight($id)
    {
        $file = $this->get($id);

        if (!in_array($file->file_type, $this->accepted_file_types))
            throw new \Exception('Tento typ súboru nemá podporovaný náhľad.');

        if (!file_exists(__DIR__ . '/../../uploads/dataset_file_' . $file->id . '.txt'))
            throw new \Exception('Súbor neexistuje.');

        $content = htmlspecialchars($this->file_get_contents_utf8(__DIR__ . '/../../uploads/dataset_file_' . $file->id . '.txt'), ENT_QUOTES | ENT_SUBSTITUTE);

        $table = [];

        $content = explode("\n", $content);

        $i = 0;
        foreach ($content as $c) {
            if ($i >= 11)
                break;

            $row = explode(';', $c);
            $table[] = $row;
            $i++;
        }

        return $table;
    }

    public function hide($id)
    {
        $file = $this->get($id);

        $hidden = 1;

        if ($file->hidden)
            $hidden = 0;

        $file->update([
            'hidden' => $hidden
        ]);

        if ($hidden)
            return true;

        return false;
    }

    public function file_get_contents_utf8($text) {

        $text = file_get_contents($text);

        if (mb_detect_encoding($text, 'utf-8, ISO-8859-2') == 'UTF-8')
            return $text;

        $map = array(
            chr(0x8A) => chr(0xA9),
            chr(0x8C) => chr(0xA6),
            chr(0x8D) => chr(0xAB),
            chr(0x8E) => chr(0xAE),
            chr(0x8F) => chr(0xAC),
            chr(0x9C) => chr(0xB6),
            chr(0x9D) => chr(0xBB),
            chr(0xA1) => chr(0xB7),
            chr(0xA5) => chr(0xA1),
            chr(0xBC) => chr(0xA5),
            chr(0x9F) => chr(0xBC),
            chr(0xB9) => chr(0xB1),
            chr(0x9A) => chr(0xB9),
            chr(0xBE) => chr(0xB5),
            chr(0x9E) => chr(0xBE),
            chr(0x80) => '&euro;',
            chr(0x82) => '&sbquo;',
            chr(0x84) => '&bdquo;',
            chr(0x85) => '&hellip;',
            chr(0x86) => '&dagger;',
            chr(0x87) => '&Dagger;',
            chr(0x89) => '&permil;',
            chr(0x8B) => '&lsaquo;',
            chr(0x91) => '&lsquo;',
            chr(0x92) => '&rsquo;',
            chr(0x93) => '&ldquo;',
            chr(0x94) => '&rdquo;',
            chr(0x95) => '&bull;',
            chr(0x96) => '&ndash;',
            chr(0x97) => '&mdash;',
            chr(0x99) => '&trade;',
            chr(0x9B) => '&rsquo;',
            chr(0xA6) => '&brvbar;',
            chr(0xA9) => '&copy;',
            chr(0xAB) => '&laquo;',
            chr(0xAE) => '&reg;',
            chr(0xB1) => '&plusmn;',
            chr(0xB5) => '&micro;',
            chr(0xB6) => '&para;',
            chr(0xB7) => '&middot;',
            chr(0xBB) => '&raquo;',
        );
        return html_entity_decode(mb_convert_encoding(strtr($text, $map), 'UTF-8', 'ISO-8859-2'), ENT_QUOTES, 'UTF-8');
    }
}
