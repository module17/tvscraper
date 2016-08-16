<?php
namespace Tvscraper;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

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

class Config {
    /**
     * MEMBER FIELDS
     * @var array|mixed $config     Description
     *
     * METHODS
     * @method
     * @method  mixed   getSetting(string $setting)
     * @method  array   getSettings()
     * @method  void    saveSetting(string $setting, mixed $value)
     * @method  void    saveSettings(mixed[] $config)
     */

    private $config = [
        "firstRun" => "1",
        "lu" => "",
        "tz" => "",
        "startTime" => "",
        "endTime" => "",
        "channelNumber" => "",
        "subChannelNumber" => "",
        "callsign" => "",
        "listingId" => "",
        "listDateTime" => "",
        "duration" => "",
        "showId" => "",
        "seriesId" => "",
        "showName" => "",
        "episodeTitle" => "",
        "episodeNumber" => "",
        "parts" => "",
        "partNumber" => "",
        "seriesPremiere" => "",
        "seasonPremiere" => "",
        "seriesFinale" => "",
        "seasonFinale" => "",
        "repeat" => "",
        "newShow" => "",
        "rating" => "",
        "captioned" => "",
        "educational" => "",
        "blackwhite" => "",
        "subtitled" => "",
        "live" => "",
        "hd" => "",
        "descriptiveVideo" => "",
        "inProgress" => "",
        "showTypeId" => "",
        "breakoutLevel" => "",
        "showType" => "",
        "year" => "",
        "guest" => "",
        "cast" => "",
        "director" => "",
        "starRating" => "",
        "description" => "",
        "league" => "",
        "team1" => "",
        "team2" => "",
        "sportEvent" => "",
        "location" => "",
        "showPicture" => "",
        "showTitle" => ""
    ];

    public function __construct() {
        try {
            $this->config = Yaml::parse(file_get_contents(__DIR__ . '/../config.yml'));

        } catch (ParseException $e) {
            throw new \Exception(sprintf('Unable to parse the YAML config file: %s', $e->getMessage()));
        }


        $this->saveSettings($this->config);

    }

    public function saveSetting($setting, $value) {
        foreach ($this->config as $k => $v) {
            if ($k == $setting) {
                $this->config[$setting] = $value;
            }
        }
        file_put_contents(__DIR__ . '/../config.yml', Yaml::dump($this->config));
    }

    public function saveSettings($config) {
        foreach ((array) $config as $k => $v) {
            $this->config[$k] = $v;
        }
        file_put_contents(__DIR__ . '/../config.yml', Yaml::dump($this->config));
    }

    public function getSetting($setting) {
        return $this->config[$setting];
    }

    public function getSettings() {
        return $this->config;
    }
}