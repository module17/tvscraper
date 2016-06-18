<?php
namespace Tvscraper;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/* tvscraper - tvpassport tv schedule scraper
 *
 * Hard-coded to use luid 41501 - Rogers cable Toronto - Digital adapter
 *
 * ie. http://tvpassport.com/tvgrid.shtml?luid=41501&st=1465584150&sch=15&size=1&tzo=-5&dsto=1#x
 *
 */

class Tvscraper {
    public $time_block = 30;
    public $debug = false;
    public $display_single = true;
    public $listing_id;
    public $start_stamp = '';
    public $timezone;
    public $dst = true;
    public $schedule_size = 4;
    public $base_url = 'http://tvpassport.com/';
    public $schedule_url = 'tvgrid.shtml?';
    public $times = array();


    /*
     * @param $config array of configuration settings
     */
    public function __construct($config = array()) {
        if (!empty($config)) {
            $this->time_block = $config['time_block'];
            $this->debug = $config['debug'];
            $this->display_single = $config['display_single'];
        }
        $this->readConfig();
    }

    public function readConfig() {
        try {
            $config = Yaml::parse(file_get_contents(__DIR__ . '/../config.yml'));
        } catch (ParseException $e) {
            throw new \Exception(sprintf("Unable to parse the YAML config file: %s", $e->getMessage()));
        }
        $this->initProvider($config['listing_id'], $config['timezone'], $config['dst']);
    }

    public function getSchedule($listing_id, $start_stamp = '', $offset, $timezone, $dst = true) {
        $params = array(
            'luid' => $listing_id, // Rogers cable Toronto - Digital adapter
            'st' => $start_stamp, // start timestamp, if null automatically starts at most recent hour mark
            'sch' => $offset, // channel listings result start [0, 21, 42, 50]
            'size' => $this->schedule_size, //shows 7.5 hours / 15 columns of data
            'tzo' => $timezone, // time zone offset
            'dtso' => intval($dst) // dst boolean
        );

        $url = sprintf('%s%s%s', $this->base_url, $this->schedule_url, http_build_query($params));

        if ($this->debug) {
            echo sprintf(PHP_EOL . 'ENDPOINT: %s' . PHP_EOL, $url);
        }

        $html = $this->fetchHTML($url);
        $bin = array();

        // get the schedule table first
        preg_match_all('/<table width="1560".*?>(.*?)<\/table>/s', $html, $table);

        // get time blocks
        preg_match_all('/<td class="TimeMark".*?>(.*?)<\/td>/', $table[1][0], $times, PREG_PATTERN_ORDER);

        $this->times = array_unique($times[1]);

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

            if ($this->display_single) {
                $stream = sprintf("%s\t%s\t\t", $chno, $chname);
            } else {
                $stream = sprintf(PHP_EOL . 'ch %s: %s - %s' . PHP_EOL, $chno, $chname, $this->times[1]);
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

                $stream .= sprintf(" %s (%dmins)" . PHP_EOL, trim($name), ($length * $this->time_block));
            }
            // append to output buffer array
            $bin[] = $stream;
        }
        return $bin;
    }

    /*
     * @param $offsets array of page offsets
     */
    public function outputSchedule($offsets = array(0)) {
        $lines = array();

        foreach ($offsets as $offset) {
            $lines = array_merge(
                $lines,
                $this->getSchedule(
                    $this->listing_id, $this->start_stamp, $offset, $this->timezone, $this->dst
                )
            );
        }

        if ($this->display_single) {
            echo sprintf('Schedule for %s' . PHP_EOL . PHP_EOL, $this->times[1]);
        }

        foreach (array_unique($lines) as $line) {
            echo $line;
        }
    }

    public function initProvider($listing_id, $timezone, $dst) {
        $this->listing_id = $listing_id;
        $this->timezone = $timezone;
        $this->dst = $dst;
    }

    /*
     * @param $url string URL to fetch
     *
     * @return $html string Response from URL endpoint
     */
    public function fetchHTML($url) {
        $html = file_get_contents($url);
        return $html;
    }
}
