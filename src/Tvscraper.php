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
    public $debug = false;
    public $timezone = '';
    public $base_url = 'http://www.tvpassport.com/';
    public $schedule_url = 'tv-listings/tvlistings/listings';
    public $provider_search_url = 'index.php/lineups';
    public $url = 'http://www.tvpassport.com/tvlistings/tvlistings/listings';

    public $postal_code = '';

    /*
     * @param $config array of configuration settings
     */
    public function __construct($config = array()) {
        if (!empty($config)) {
            $this->time_block = $config['time_block'];
            $this->debug = $config['debug'];
            $this->display_single = $config['display_single'];
        }
        $this->outputBanner();
        $this->readConfig();
    }

    public function outputBanner() {
        echo <<<DATA
********************************************************************************
******************************* tvscraper **************************************
********************************************************************************

DATA;
    }

    public function readConfig() {
        try {
            $config = Yaml::parse(file_get_contents(__DIR__ . '/../config.yml'));
        } catch (ParseException $e) {
            throw new \Exception(sprintf('Unable to parse the YAML config file: %s', $e->getMessage()));
        }
        $this->initProvider($config['listing_id'], $config['timezone']);
    }

    public function getLineups() {
        $postalCode = readline('Enter your postal/zip code: ');
        if ($postalCode && preg_match('/^[0-9A-Za-z]{5,6}$/', $postalCode)) {

            if ($this->debug) {
                echo sprintf(PHP_EOL . 'POST %s to %s%s' . PHP_EOL . PHP_EOL, $postalCode,$this->base_url, $this->provider_search_url);
            }

            $url = sprintf('%s%s', $this->base_url, $this->provider_search_url);
            $html = $this->fetchHTML($url, 'POST', array('postalCode' => $postalCode));

            if ($html) {
                // get lineup options
                preg_match_all("/<a href='http:\/\/www.tvpassport.com\/lineups\/set\/(.*?)\?lineupname=(.*?)&tz='>(.*?)<\/a>/ms", $html, $lineupNames);

                if($this->debug) {
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

                    for ($i=0; $i < sizeof($lineupNames[0]); $i++) {
                        $code= trim($lineupNames[1][$i]);
                        $name = trim($lineupNames[3][$i]);

                        echo sprintf("%d\t%s (%s)" . PHP_EOL, $i, $name, $code);
                    }

                    echo PHP_EOL;
                    $choice = readLine('Lineup Choice: ');
                    echo PHP_EOL .PHP_EOL;

                    $this->getSchedule($lineupNames[1][$choice], $this->timezone);
                    return;
                }
            } else {
                throw new \Exception('Error searching for provider. Try again.');
            }
        } else {
            $this->getLineups();
        }
    }

    public function getSchedule($code, $timezone) {
        $params = array(
            'lu' => $code,
            'st' => time(), // start timestamp, if null automatically starts at most recent hour mark
            'et' => time(),
            'tz' => $timezone
        );

        if ($this->debug) {
            var_dump($params);
        }

        $url = 'http://www.tvpassport.com/tvlistings/tvlistings/listings';

        if ($this->debug) {
            echo sprintf(PHP_EOL . 'ENDPOINT: %s' . PHP_EOL, $url);
        }

        $html = $this->fetchHTML($url, 'POST', $params);

        if ($this->debug) {
            var_dump($html);
        }

        // <div class="listing_cell" style="width:21.66%" data-st="1469502000" data-et="1469505900" data-channelnumber="49" data-subchannelnumber="0" data-callsign="BET" data-listingid="358364980" data-listdatetime="2016-07-25 23:00:00" data-duration="65" data-showid="261468" data-seriesid="97544" data-showname="Bobby Jones Gospel" data-episodetitle="" data-episodenumber="Generic                  " data-parts="0" data-partnum="0" data-seriespremiere="" data-seasonpremiere="" data-seriesfinale="" data-seasonfinale="" data-repeat="" data-new_show="" data-rating="TVG" data-captioned="1" data-educational="" data-blackwhite="" data-subtitled="" data-live="" data-hd="" data-descriptivevideo="" data-inprogress="" data-showtypeid="U" data-breakoutlevel="3" data-showtype="Religious, Music" data-year="" data-guest="" data-cast="" data-director="" data-starrating="0" data-description="Dr. Bobby Jones hosts a soulful hour of spiritual performances. " data-league="" data-team1="" data-team2="" data-sport_event="" data-location="" data-showpicture="9919.jpg" data-showtitle=" Bobby Jones Gospel - "><div class="listing_titles"> <p class="showtitle"> Bobby Jones Gospel</p> </div></div>
        // get the show data
        preg_match_all('/data-et="(.*?)".*?data-channelNumber="(.*?)".*?data-subChannelNumber="(.*?)".*?data-callsign="(.*?)".*?data-duration="(.*?)".*?data-showTitle="(.*?)"/ms', $html, $showData);

        if ($this->debug) {
            var_dump($showData);
        }

        for ($i=0;$i<sizeof($showData[0]);$i++) {
            $timeLeft = $this->timeRemaining($showData[1][$i]);
            // check if we have a sub-channel number
            $subChannel = trim($showData[3][$i]);
            if ($subChannel) {
                $channel = sprintf("%s-%s", trim($showData[2][$i]), trim($showData[3][$i]));
            } else {
                $channel = trim($showData[2][$i]);
            }
            $callsign = str_pad(trim($showData[4][$i]), 5, ' ');
            $duration = trim($showData[5][$i]);
            $showTitle = rtrim(trim($showData[6][$i])," -");
            echo sprintf("%s - %s\t(%s mins %s left)\t%s" . PHP_EOL, $channel, $callsign, $duration, $timeLeft, $showTitle);
        }
    }

    public function initProvider($listing_id, $timezone) {
        $this->listing_id = $listing_id;
        $this->timezone = $timezone;
    }

    public function timeRemaining($showET) {
        return round(($showET - time()) / 60);
    }

    /*
     * @param $url string URL to fetch
     *
     * @return $html string Response from URL endpoint
     */
    public function fetchHTML($url, $method = 'GET', $post_fields = array()) {
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
