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
            throw new \Exception('File doesn\'t exists');

        return $file;
    }

    public function create($name, $file, $users, $dataset)
    {
        if (!$file->isOk())
            throw new \Exception('Some problem occured with uploading file.');

        $end = explode('.', $file->getSanitizedName());
        $end = array_pop($end);

        $row = $this->db->table('dataset_files')->insert([
            'name' => $name,
            'users' => $users,
            'dataset' => $dataset,
            'file_type' => $end,
        ]);

        try {
            $file->move(__DIR__ . '/../../uploads/dataset_file_' . $row->id . '.txt');
        }
        catch (\Exception $e) {
            $row->delete();
            throw new \Exception('Some problem occured with uploading file.');
        }
    }

    public function edit($id, $name)
    {
        $file = $this->get($id);

        $file->update([
            'name' => $name,
        ]);
    }

    public function delete($id)
    {
        $file = $this->get($id);
        FileSystem::delete(__DIR__ . '/../../uploads/dataset_file_' . $file->id . '.txt');
        $file->delete();
    }

    public function download($id)
    {
        $file = $this->get($id);
        $dataset = $this->dataset->get($file->dataset);

        $content = file_get_contents(__DIR__ . '/../../uploads/dataset_file_' . $file->id . '.txt');

        $this->req->setHeader('Content-Type', 'application/octet-stream');
        $this->req->setHeader('Content-Disposition', 'attachment; filename=dataset.'.$dataset->uniq_id.'.'.$file->file_type);
        $this->req->setHeader('Content-Length', strlen($content));
        echo $content;
        exit;
    }

    public function insight($id)
    {
        $file = $this->get($id);

        if (!in_array($file->file_type, $this->accepted_file_types))
            throw new \Exception('Tento typ súboru nemá podporovaný náhľad.');

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
