<?php

/* tvscraper - tvpassport tv schedule scraper
 *
 * Hard-coded to use luid 41501 - Rogers cable Toronto - Digital adapter
 *
 * ie. http://tvpassport.com/tvgrid.shtml?luid=41501&st=1465584150&sch=15&size=1&tzo=-5&dsto=1#x
 *
 */

$url = 'http://tvpassport.com/tvgrid.shtml?';

// time in minutes for each colspan in schedule table
define('TIME', 30);
$debug = false;
$single = true;

/*
* luid = Cable provider ID
*/
foreach (array(0,21,42,50) as $offset) {
    $params = array(
        'luid' => '41501', // Rogers cable Toronto - Digital adapter
        'st' => '', // start timestamp, if null automatically starts at most recent hour mark
        'sch' => $offset, // channel listings result start [0, 21, 42, 50]
        'size' => 4, //shows 7.5 hours / 15 columns of data
        'tzo' => '-5', // time zone offset
        'dtso' => '1' // dst boolean
    );

    $end = sprintf('%s%s', $url, http_build_query($params));

    if ($debug) {
        echo sprintf(PHP_EOL . 'ENDPOINT: %s' . PHP_EOL, $end);
    }

    // need all 4 pages
    $d = file_get_contents($end);

    // TODO: Implement caching
    //file_put_contents($offset . 'tvlisting.html', $d, FILE_APPEND);
    //$d = file_get_contents(($offset . 'tvlisting.html');

    // get the schedule table first
    preg_match_all('/<table width="1560".*?>(.*?)<\/table>/s', $d, $table);

    // get time blocks
    preg_match_all('/<td class="TimeMark".*?>(.*?)<\/td>/', $table[1][0], $times, PREG_PATTERN_ORDER);

    $times = array_unique($times[1]);

    // get rows
    preg_match_all('/<tr>(.*?)<\/tr>/', $table[1][0], $rows, PREG_PATTERN_ORDER);
    $rows = $rows[1];

    // per row operations, first and last rows are time markers
    foreach (array_slice($rows, 1, -1) as $r => $v) {
        // get all table data cells
        preg_match_all('/<td.*?>(.*?)<\/td>/', $v, $tds, PREG_PATTERN_ORDER);
        $tdr = $tds[0];
        $tds = $tds[1];
        $chno = $tds[0];
        $chname = $tds[1];

        // strip out html entities and trim
        $chname = str_replace('&nbsp;', '', $chname);
        $chname = trim($chname);
        $chimg = $tds[2];

        if ($single) {
            $stream = sprintf("%s\t%s\t\t", $chno, $chname);
        } else {
            $stream = sprintf(PHP_EOL . 'ch %s: %s - %s' . PHP_EOL, $chno, $chname, $times[1]);
        }

        // TODO: Allow arguments to control how many shows are displayed
        // remaining rows 3 - end of array are shows, need to get colspan to determine length of show
        $future = 1; //number of shows to show
        //$future = sizeof($tdr); // show all

        for ($i = 3;$i < 3 + $future; $i++) {
            $show = trim($tdr[$i]);
            // get show name and colspan
            preg_match_all('/<td colspan="(.)".*?>(.*?)<\/td>/', $show, $showd, PREG_PATTERN_ORDER);
            $length = intval($showd[1][0]);
            $name = utf8_encode(trim($showd[2][0]));

            // extract movie image identifier and create label
            $name = str_replace(
                array(
                    '<img src="/images/moviecamera3.gif" align="left"><b>',
                    '<b>',
                    '</b>',
                    '  '
                ),
                array(
                    '', '', '', ' '
                ),
                $name
            );

            $stream .= sprintf(" %s (%dmins)" . PHP_EOL, trim($name), ($length * TIME));
        }
        // append to output buffer array
        $bin[] = $stream;
    }
}

if ($single) {
    echo sprintf('Schedule for %s' . PHP_EOL . PHP_EOL, $times[1]);
}

foreach (array_unique($bin) as $sh) {
    echo $sh;
}
