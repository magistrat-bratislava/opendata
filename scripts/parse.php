<?php

if (!isset($argv[1]) || !isset($argv['2']))
    die("Usage: parse.php [type] [file]\n");

$types = ['summary', 'form', 'locations'];
$form_types = ['device_type', 'os', 'browser', 'lang'];
$type = $argv[1];
$file = $argv[2];

if (!in_array($type, $types))
    die("Types: ".implode(' | ', $types)."\n");

if (!file_exists($file))
    die("File ".$file." doesn't exists.\n");

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;
$configurator->setTempDirectory('../temp');
$configurator->addConfig('../app/config/db.neon');
$container = $configurator->createContainer();
$db = $container->getByType(Nette\Database\Context::class);

function pair($header, $row, $has_time = false)
{
    if (count($header) != count($row)) {
        echo 'WARNING: Columns in line ('.count($row).') doesn\'t match header ('.count($header).') '."\n";
        return NULL;
    }

    $paired = [];
    $paired['date'] = $row[0] . ' 00:00:00';

    for ($i = ($has_time ? 2 : 1); $i < count($header); $i++)
        $paired[$header[$i]] = $row[$i];

    return $paired;
}

function read_file($filename, $has_time = false)
{
    $f = fopen($filename, 'r') or die('Can\'t read file: ' . $filename);
    $data = fread($f, filesize($filename));

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
        $pair = pair($header, $row, $has_time);

        if ($pair == NULL)
            continue;

        $parse[] = $pair;
    }

    return $parse;
}

$rows_insert = 0;
$rows_delete = 0;
$by_date = [];

switch ($type) {
    case 'summary':
        $data = read_file($file, true);

        foreach ($data as $d) {
            if (!isset($by_date[$d['date']])) {
                $by_date[$d['date']] = [
                    'new_users' => $d['new_users'],
                    'returning_users' => $d['returning_users'],
                    'average_online' => $d['average_online'],
                    'max_online' => $d['max_online'],
                    'dwell_5m' => $d['dwell_5m'],
                    'dwell_10m' => $d['dwell_10m'],
                    'dwell_30m' => $d['dwell_30m'],
                    'dwell_60m' => $d['dwell_60m'],
                    'dwell_long' => $d['dwell_long'],
                ];
            } else {
                $by_date[$d['date']]['new_users'] += $d['new_users'];
                $by_date[$d['date']]['returning_users'] += $d['returning_users'];
                $by_date[$d['date']]['average_online'] += $d['average_online'];
                $by_date[$d['date']]['max_online'] += $d['max_online'];
                $by_date[$d['date']]['dwell_5m'] += $d['dwell_5m'];
                $by_date[$d['date']]['dwell_10m'] += $d['dwell_10m'];
                $by_date[$d['date']]['dwell_30m'] += $d['dwell_30m'];
                $by_date[$d['date']]['dwell_60m'] += $d['dwell_60m'];
                $by_date[$d['date']]['dwell_long'] += $d['dwell_long'];
            }
        }

        foreach ($by_date as $date => $d) {
            $exists = $db->table('onlinedata_summary')->where('data_date', $date)->fetch();

            if ($exists) {
//                $exists->delete();
//                $rows_delete += 1;

                echo "Date ".$date." exists.\n";
            } else {
                $db->table('onlinedata_summary')->insert([
                    'data_date' => $date,
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

                $rows_insert += 1;
            }
        }
        break;

    case 'form':
        $data = read_file($file, false);

        foreach ($data as $d) {
            if (!isset($by_date[$d['date']])) {
                foreach ($form_types as $type) {
                    $by_date[$d['date']][$type][$d[$type]] = 1;
                }
            } else {
                foreach ($form_types as $type) {
                    if (isset($by_date[$d['date']][$type][$d[$type]]))
                        $by_date[$d['date']][$type][$d[$type]] += 1;
                    else
                        $by_date[$d['date']][$type][$d[$type]] = 1;
                }
            }
        }

        foreach ($by_date as $date => $d) {
            foreach ($form_types as $form_type) {
                foreach ($d[$form_type] as $name => $value) {

                    $exists = $db->table('onlinedata_form')
                        ->where('data_date', $date)
                        ->where('type', $form_type)
                        ->where('name', $name)
                        ->fetch();

                    if ($exists) {
//                        $exists->delete();
//                        $rows_delete += 1;

                        echo "Date: ".$date.", type: ".$form_type.", name: ".$name." exists.\n";
                    } else {

                        $db->table('onlinedata_form')->insert([
                            'data_date' => $date,
                            'type' => $form_type,
                            'name' => $name,
                            'value' => $value,
                        ]);

                        $rows_insert += 1;
                    }
                }
            }
        }
        break;
    default:
        $data = read_file($file, true);

        foreach ($data as $d) {
            if (!isset($by_date[$d['date']][$d['location']]))
                $by_date[$d['date']][$d['location']] = $d['new_users'];
            else
                $by_date[$d['date']][$d['location']] += $d['new_users'];

//            if (!isset($by_date[substr($d['date'],0,7)][$d['location']])) {
//                $by_date[substr($d['date'],0,7)][$d['location']] = $d['new_users'];// + $d['returning_users'];
//            } else {
//                $by_date[substr($d['date'],0,7)][$d['location']] += $d['new_users'];// + $d['returning_users'];
//            }
        }

        foreach ($by_date as $date => $d) {
//            $date = $date . '-01 00:00:00';

            foreach ($d as $location => $value) {

                $exists = $db->table('onlinedata_locations')
                    ->where('data_date', $date)
                    ->where('location', $location)
                    ->fetch();

                if ($exists) {
//                    $exists->delete();
//                    $rows_delete += 1;

                    echo "Date: ".$date.", name: ".$location." exists.\n";
                } else {

                    $db->table('onlinedata_locations')->insert([
                        'data_date' => $date,
                        'location' => $location,
                        'value' => $value,
                    ]);

                    $rows_insert += 1;
                }

//                $exists = $db->table('onlinedata_locations')
//                    ->where('data_date', $date)
//                    ->where('location', $location)
//                    ->fetch();
//
//                if ($exists) {
//                    $exists->update([
//                        'value' => $exists->value + $value,
//                    ]);
//                } else {
//                    $db->table('onlinedata_locations')->insert([
//                        'data_date' => $date,
//                        'location' => $location,
//                        'value' => $value,
//                    ]);
//
//                    $rows_insert += 1;
//                }

            }
        }
        break;
}

echo "Inserted ".$rows_insert." rows.\n";
echo "Deleted ".$rows_delete." rows.\n";
