<?php

if (!isset($argv[1]) || !isset($argv['2']))
    die("Usage: parse_old_data.php [type] [file] [?form-type]\n");

$types = ['summary', 'form', 'locations'];
$type = $argv[1];
$file = $argv[2];

if (!in_array($type, $types))
    die("Types: ".implode(' | ', $types)."\n");

if (!file_exists($file))
    die("File ".$file." doesn't exists.\n");

$form_type = NULL;
if ($type == 'form') {
    if (!isset($argv[3]))
        die("Usage: parse_old_data.php form [file] [form-type]\n");

    $form_types = ['device_type', 'os', 'browser', 'lang'];
    $form_type = $argv[3];

    if (!in_array($form_type, $form_types))
        die("Form types: ".implode(' | ', $form_types)."\n");
}

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;
$configurator->setTempDirectory('../temp');
$configurator->addConfig('../app/config/db.neon');
$container = $configurator->createContainer();
$db = $container->getByType(Nette\Database\Context::class);

function pair($header, $row, $has_day = false)
{
    if (count($header) != count($row)) {
        echo 'WARNING: Columns in line ('.count($row).') doesn\'t match header ('.count($header).') '."\n";
        return NULL;
    }

    $paired = [];
    $paired['date'] = $has_day ? $row[0] : $row[0] . '-01';
    $paired['date'] = $paired['date'] . ' 00:00:00';

    for ($i = 1; $i < count($header); $i++)
        $paired[$header[$i]] = $row[$i];

    return $paired;
}

function read_file($filename, $has_day = false)
{
    $f = fopen($filename, 'r') or die('Can\'t read file: ' . $filename);
    $data = fread($f, filesize($filename));

    $data = str_replace("\r", "", $data);
    $data = explode("\n", $data);
    $header = [];

    if (count($data) > 1 && isset($data[0])) {
        $header = explode(";", str_replace("\"", "", $data[0]));
        unset($data[0]);
    } else
        die('Can\'t find data in file: ' . $filename);

    fclose($f);

    $parse = [];

    foreach ($data as $line) {
        if (empty($line))
            continue;

        $line = str_replace("\"", "", $line);
        $row = explode(";", html_entity_decode($line));
        $pair = pair($header, $row, $has_day);

        if ($pair == NULL)
            continue;

        $parse[] = $pair;
    }

    return $parse;
}

switch ($type) {
    case 'summary':
        $data = read_file($file, true);

        foreach ($data as $d) {
            $db->table('onlinedata_summary')->insert([
                'data_date' => $d['date'],
                'new_users' => $d['new_users'],
                'returning_users' => $d['returning_users'],
                'average_online' => $d['average_online'],
                'max_online' => $d['max_online'],
                'dwell_5m' => $d['dwell_5m'],
                'dwell_10m' => $d['dwell_10m'],
                'dwell_30m' => $d['dwell_30m'],
                'dwell_60m' => $d['dwell_60m'],
                'dwell_long' => $d['dwell_long'],
            ]);
        }
        break;
    case 'form':
        $data = read_file($file, true);

        foreach ($data as $d) {
            $db->table('onlinedata_form')->insert([
                'data_date' => $d['date'],
                'type' => $form_type,
                'name' => $d[$form_type],
                'value' => $d['value'],
            ]);
        }
        break;
    default:
        $data = read_file($file, false);

        foreach ($data as $d) {
            $db->table('onlinedata_locations')->insert([
                'data_date' => $d['date'],
                'location' => $d['location'],
                'value' => $d['value']
            ]);
        }
        break;
}

echo "Inserted ".count($data)." rows.\n";
