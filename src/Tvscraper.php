<?php
namespace Tvscraper;

require_once('Config.php');

/**
 * tvscraper - tvpassport tv schedule scraper
 *
 * @author module17
 * @author Fenrisulfir
 *
 * @package tvscraper
 *
 * @version 2.01
 *
 */

class Tvscraper {
    /**
     * DEBUGGING FLAGS
     * @var boolean $debugLineupNames   Display Broadcast Provider Array
     * @var boolean $debugPostalCode    Display Postal Code and URL
     * @var boolean $debugUsingParams   Display Params Used To Generate ShowData
     * @var boolean $debugEndpoint      Display ShowData URL
     * @var boolean $debugDataPattern   Display ShowData Array
     *
     * MEMBER FIELDS
     * @var string      $base_url       TVPassport URL
     * @var string      $schedule_url   ShowData URL
     * @var string      $provider_url   BroadCast Provider URL
     * @var Config      $config         Configuration Array
     * @var string[]    $showData       ShowData Array
     *
     * METHODS
     * @method          __construct()
     * @method void     outputBanner()
     * @method void     run()
     * @method void     setTimeZone()
     * @method void     setDisplayOptions()
     * @method mixed    validateInput(string $input, FILTER_TYPE $filter)
     * @method void     getLineups()
     * @method void     getSchedule(string $code, string $timezone)
     * @method void     buildPattern(string $pattern, string &$html)
     * @method string   fetchHTML(string $url, string $method, string[] $post_fields)
     *
     */
    public $debugLineupNames = false;
    public $debugPostalCode = true;
    public $debugUserParams = true;
    public $debugEndPoint = true;
    public $debugDataPattern = true;

    public $baseUrl = 'http://www.tvpassport.com/';
    public $scheduleUrl = 'tvlistings/tvlistings/listings';
    public $providerSearchUrl = 'index.php/lineups';

    protected $config;

    protected $showData = array();

    /**
     * Constructorizationify
     */
    public function __construct() {
        $this->config = new Config();
        $this->outputBanner();
    }

    /**
     * Claim this console output in the name of tvscraper
     */
    public function outputBanner() {
        echo <<<DATA
********************************************************************************
******************************* tvscraper **************************************
********************************************************************************

DATA;
    }

    /**
     * Initialize config options
     *
     * @return void
     *
     * @todo create better FSM
     */
    public function run() {
        $input = readline('Do you want to enter your timezone? ');
        if ($this->validateInput($input, FILTER_VALIDATE_BOOLEAN) || $this->config->getSetting("firstRun")) {
            $this->setTimeZone();
        }

        $input = readline('Do you want to setup your display options? ');
        if ($this->validateInput($input, FILTER_VALIDATE_BOOLEAN) || $this->config->getSetting("firstRun")) {
            $this->setDisplayOptions();
        }

        $config['firstRun'] = false;
        $this->config->saveSettings($config);
        $this->getLineups();

    }

    /**
     * Validate user input
     *
     * @param mixed     $input  User input data to be validated
     * @param filter    $filter Type of filter used to validate input
     *
     * @return  mixed   NULL if validation fails
     *
     * @todo sanitize input
     * @todo handle different data types
     * @todo pass all input through here
     */
    public function validateInput($input, $filter) {

        if ($filter === FILTER_VALIDATE_BOOLEAN) {
            if (strtolower($input) == 'y') {
                $input = 'true';
            } elseif (strtolower($input) == 'n') {
                $input = 'false';
            }
        }

        if ($input == '\n') {
            $input = 'false';
        }

        return filter_var($input, $filter, FILTER_NULL_ON_FAILURE);

    }

    /**
     * Reads user input and searches a precompiled list to select the appropriate IANA timezone
     *
     * @return void
     *
     * @todo create greedy search function for timezone cuz I'm lazy
     */
    public function setTimezone() {
        $tz = readline('Enter your timezone (ie, America/Toronto): ');

        $timeZoneList = \DateTimeZone::listIdentifiers();

        foreach ($timeZoneList as $timeZone) {
            if ($timeZone === $tz) {
                $config['tz'] = $tz;
                $this->config->saveSettings($config);
                break;
            }
        }
    }

    /**
     * Interactively allows the user to choose which data points to display and saves each option to an array
     *
     * @return void
     */
    public function setDisplayOptions() {
        $count = 0;
        foreach ($this->config->getSettings() as $k => $v) {
            if ($count > 4) {
                $valid = false;
                while ($valid == false) {

                    $input = readline('Do you want to see the ' . $k . '? [Y/n] - Defaults to n: ');
                    $boolean = $this->validateInput($input, FILTER_VALIDATE_BOOLEAN);
                    if (is_null($boolean)) {
                        echo 'Invalid input!  Smarten up.' . PHP_EOL . PHP_EOL;
                    } else {
                        $this->config->saveSetting($k, $boolean);
                        $valid = true;
                    }
                }
            }
            ++$count;
        }
    }

    /**
     * Gets a list of broadcast providers based on the users postal code.
     *
     * @throws \Exception
     */
    public function getLineups() {
        $postalCode = readline('Enter your postal/zip code: ');
        if ($postalCode && preg_match('/^[0-9A-Za-z]{5,6}$/', $postalCode)) {
            if ($this->debugPostalCode) {
                echo sprintf(PHP_EOL . 'POST %s to %s%s' . PHP_EOL . PHP_EOL, $postalCode, $this->baseUrl, $this->providerSearchUrl);
            }
            $url = sprintf('%s%s', $this->baseUrl, $this->providerSearchUrl);
            $html = $this->fetchHTML($url, 'POST', array('postalCode' => $postalCode));

            if ($html) {
                // get lineup options
                preg_match_all("/<a href='http:\/\/www.tvpassport.com\/lineups\/set\/(.*?)\?lineupname=(.*?)&tz='>(.*?)<\/a>/ms", $html, $lineupNames);

                if ($this->debugLineupNames) {
                    var_dump($lineupNames);
                }

                if (sizeof($lineupNames[0]) < 1) {
                    echo sprintf(
                        'Cannot find any service area providers for postal/zip code: %s' . PHP_EOL . PHP_EOL,
                        $postalCode
                    );
                    $this->getLineups();

                    return;
                } else {

                    echo PHP_EOL . 'Please select your lineup from the list below.' . PHP_EOL;
                    for ($i = 0; $i < sizeof($lineupNames[0]); $i++) {
                        $code = trim($lineupNames[1][$i]);
                        $name = trim($lineupNames[3][$i]);

                        echo sprintf("%d\t%s (%s)" . PHP_EOL, $i, $name, $code);
                    }
                    echo PHP_EOL;
                    $choice = readLine('Lineup Choice: ');
                    echo PHP_EOL . PHP_EOL;

                    $config['lu'] = $lineupNames[1][$choice];
                    $this->config->saveSettings($config);

                    $this->getSchedule($this->config->getSetting('lu'), $this->config->getSetting('tz'));
                    return;
                }
            } else {
                throw new \Exception('Error searching for provider. Try again.');
            }
        } else {
            $this->getLineups();
        }
    }

    /**
     * Uses the broadcast code and timezone to get all of the shows currently playing.
     *
     * @param int       $code       The code used to represent the Broadcast Provider Lineup
     * @param string    $timezone   The timezone
     *
     * @return void
     *
     * @todo build the regex pattern more gracefully
     * @todo figure out how to nicely display potentially all the data
     * @todo sanitize the data before displaying it
     * @todo display data headings
     * @todo handle missing data causing different sized arrays
     */
    public function getSchedule($code, $timezone) {
        $params = array(
            'lu' => $code,
            'st' => time(), // start timestamp, if null automatically starts at most recent hour mark
            'et' => time(),  //end timestamp, set to same as st to find only currently playing shows
            'tz' => $timezone
        );

        if ($this->debugUserParams) {
            echo 'Params:' . PHP_EOL;
            var_dump($params);
        }

        $url = $this->baseUrl . $this->scheduleUrl;
        if ($this->debugEndPoint) {
            echo sprintf(PHP_EOL . 'ENDPOINT: %s' . PHP_EOL . PHP_EOL, $url);
        }

        $html = $this->fetchHTML($url, 'POST', $params);

        $count = 0;
        // get the show data
        if ($this->config->getSetting('startTime')) {
            $pattern = 'data-st="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('endTime')) {
            $pattern = 'data-et="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('channelNumber')) {
            $pattern = 'data-channelNumber=\"(.*?)\"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('subChannelNumber')) {
            $pattern = 'data-subChannelNumber="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('callsign')) {
            $pattern = 'data-callsign="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('listingId')) {
            $pattern = 'data-listingid="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('listDateTime')) {
            $pattern = 'data-listdatetime="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('duration')) {
            $pattern = 'data-duration="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('showId')) {
            $pattern = 'data-showid="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('seriesId')) {
            $pattern = 'data-seriesid="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('showName')) {
            $pattern = 'data-showname="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('episodeTitle')) {
            $pattern = 'data-episodetitle="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('episodeNumber')) {
            $pattern = 'data-episodenumber="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('parts')) {
            $pattern = 'data-parts="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('partNumber')) {
            $pattern = 'data-partnum="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('seriesPremiere')) {
            $pattern = 'data-seriespremiere="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('seasonPremiere')) {
            $pattern = 'data-seasonpremiere="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('seriesFinale')) {
            $pattern = 'data-seriesfinale="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('seasonFinale')) {
            $pattern = 'data-seasonfinale="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('repeat')) {
            $pattern = 'data-repeat="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('newShow')) {
            $pattern = 'data-newshow="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('rating')) {
            $pattern = 'data-rating="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('captioned')) {
            $pattern = 'data-captioned="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('educational')) {
            $pattern = 'data-educational="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('blackwhite')) {
            $pattern = 'data-blackwhite="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('subtitled')) {
            $pattern = 'data-subtitled="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('live')) {
            $pattern = 'data-live="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('hd')) {
            $pattern = 'data-hd="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('descriptiveVideo')) {
            $pattern = 'data-descriptivevideo="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('inProgress')) {
            $pattern = 'data-inprogress="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('showTypeId')) {
            $pattern = 'data-showtypeid="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('breakoutLevel')) {
            $pattern = 'data-breakoutlevel="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('showType')) {
            $pattern = 'data-showtype="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('year')) {
            $pattern = 'data-year="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('guest')) {
            $pattern = 'data-guest="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('cast')) {
            $pattern = 'data-cast="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('director')) {
            $pattern = 'data-director="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('starRating')) {
            $pattern = 'data-starrating="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('description')) {
            $pattern = 'data-description="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('league')) {
            $pattern = 'data-league="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('team1')) {
            $pattern = 'data-team1="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('team2')) {
            $pattern = 'data-team2="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('sportEvent')) {
            $pattern = 'data-sport_event="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('location')) {
            $pattern = 'data-location="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('showPicture')) {
            $pattern = 'data-showPicture="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }
        if ($this->config->getSetting('showTitle')) {
            $pattern = 'data-showTitle="(.*?)"';
            $this->buildPattern($pattern, $html);
            ++$count;
        }

        if ($this->debugDataPattern) {
            var_dump($this->showData);
        }

        //Number of shows
        for ($i = 0; $i < sizeof($this->showData[0][0]); ++$i) {
            //Number of data points
            for ($j = 0; $j < $count; ++$j) {
                echo sprintf("%s\t", $this->showData[$j][1][$i]);
            }
            echo PHP_EOL;
        }

    }

    /**
     * Scrapes the data from the HTML and pushes it into the showData array
     *
     * @param string    $pattern  Regex pattern for each data point
     * @param string    $html     The scraped html
     *
     * @return void
     *
     * @todo stop adding raw data to showData array
     */
    public
    function buildPattern($pattern, $html) {
        preg_match_all('/' . $pattern . '/ms', $html, $arr);

        array_push($this->showData, $arr);
    }

    /**
     * @param   string  $url            URL to fetch
     * @param   string  $method         'POST' or 'GET'
     * @param   mixed[] $post_fields
     *
     * @return  string  $html           Response from URL endpoint
     *
     * @todo handle invalid method
     */
    public
    function fetchHTML($url, $method = 'GET', $post_fields = array()) {
        if ($method == 'GET') {
            $html = file_get_contents($url);
            return $html;
        } elseif ($method == 'POST') {
            // curl post fields here
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $html = curl_exec($ch);
            curl_close($ch);
            return $html;
        }
    }
}
