<?php

class Trophies
{
    // isk values
    // be in tournament region
    // freighter burn 

    public static $conditions = [
        ['type' => 'General', 'name' => 'Get a solo kill', 'filter' => ['characterID' => '?', 'isVictim' => false, 'solo' => true], 'rank' => 2, 'link' => '../solo/kills/'],
        ['type' => 'General', 'name' => 'Kill Kill Kill', 'stats' => ['field' => 'shipsDestroyed', 'value' => 1], 'link' => '../kills/'],
        ['type' => 'General', 'name' => 'Didn\'t want that ship anyway (Losses)', 'stats' => ['field' => 'shipsLost', 'value' => 1], 'link' => '../losses/'],
        ['type' => 'Special', 'name' => 'Concordokken! Get concorded', 'filter' => ['isVictim' => false, 'corporationID' => 1000125, 'compare' => true], 'rank' => '1', 'link' => '../losses/reset/corporation/1000125/kills/'],
        ['type' => 'Special', 'name' => 'What did you do?! Get killed by a CCP dev', 'filter' => ['isVictim' => false, 'corporationID' => 109299958, 'compare' => true], 'rank' => 125, 'link' => '../losses/reset/corporation/109299958/kills/'],
        ['type' => 'Special', 'name' => 'Banhammer incoming! Kill a CCP dev', 'filter' => ['isVictim' => true, 'corporationID' => 109299958, 'compare' => true], 'rank' => 5000, 'link' => '../kills/reset/corporation/109299958/losses/'],
        ['type' => 'General', 'name' => 'Get a kill in high sec', 'filter' => ['characterID' => '?', 'isVictim' => false, 'highsec' => true], 'rank' => 1, 'link' => '../kills/highsec/'],
        ['type' => 'General', 'name' => 'Get a kill in low sec', 'filter' => ['characterID' => '?', 'isVictim' => false, 'lowsec' => true], 'rank' => 5, 'link' => '../kills/lowsec'],
        ['type' => 'General', 'name' => 'Get a kill in null sec', 'filter' => ['characterID' => '?', 'isVictim' => false, 'nullsec' => true], 'rank' => 25, 'link' => '../kills/nullsec/'],
        ['type' => 'General', 'name' => 'Get a kill in w-space', 'filter' => ['characterID' => '?', 'isVictim' => false, 'w-space' => true], 'rank' => 125, 'link' => '../kills/w-space/'],
        ['type' => 'Special', 'name' => 'Participate in a tournament', 'filter' => ['regionID' => 10000004, 'characterID' => '?'], 'rank' => 5000, 'link' => '../regionID/10000004'],
        ['type' => 'Special', 'name' => 'GANKED: suicide inspired killmail', 'filter' => ['characterID' => '?', 'isVictim' => false, 'ganked' => true], 'rank' => 25, 'link' => '../ganked/'],
        ['type' => 'Special', 'name' => 'Ganktastic Bonus: Freighters must die', 'statGroup' => ['groupID' => 513, 'field' => 'shipsDestroyed', 'value' => 1], 'rank' => 625, 'link' => '../reset/group/513/losses/'],

        ['type' => 'Special', 'name' => 'Backstab Special: You awoxed!', 'filter' => ['characterID' => '?', 'isVictim' => false, 'awox' => true], 'rank' => 25, 'link' => '../awox/kills/'],
        ['type' => 'Special', 'name' => 'My Back Hurts: Got awoxed!', 'filter' => ['characterID' => '?', 'isVictim' => true, 'awox' => true], 'rank' => 25, 'link' => '../awox/losses/'],
        ];

    public static function getTrophies($charID)
    {
        global $mdb;

        $charID = (int) $charID;
        $type = 'characterID';

        $stats = $mdb->findDoc('statistics', ['type' => $type, 'id' => $charID]);
        $trophies = [];
        $maxLevelCount = 0;
        $levelCount = 0;

        foreach (static::$conditions as $condition) {
            $maxLevelCount += 5;
            if (isset($condition['filter'])) {
                $filter = $condition['filter'];
                if (isset($filter['characterID'])) {
                    $filter['characterID'] = $charID;
                }
                $query = MongoFilter::buildQuery($filter);
                if (isset($filter['compare'])) {
                    $part2 = ['characterID' => $charID, 'isVictim' => !$filter['isVictim']];
                    $part2 = MongoFilter::buildQuery($part2);
                    $query = ['$and' => [$query, $part2]];
                }
                $count = $mdb->count('killmails', $query);
                $levelCount += static::addTrophy($trophies, $condition, $count > 0, $count);

            }
            if (isset($condition['stats'])) {
                $field = $condition['stats']['field'];
                $value = $condition['stats']['value'];
                $met = @$stats[$field] >= $value;
                $levelCount += static::addTrophy($trophies, $condition, $met, (int) @$stats[$field]);
            }
            if (isset($condition['statGroup'])) {
                $group = @$stats['groups'][$condition['statGroup']['groupID']];
                $field = $condition['statGroup']['field'];
                $value = $condition['statGroup']['value'];
                $levelCount += static::addTrophy($trophies, $condition, @$group[$field] >= $value, @$group[$field]);
            }
        }

        $groups = $mdb->find('information', ['type' => 'groupID', 'cacheTime' => 3600], ['name' => 1]);

        foreach ($groups as $row) {
            if (@$row['categoryID'] != 6 || @$row['published'] != true) {
                continue;
            }
            $groupID = (int) $row['id'];
            $count = $mdb->count('information', ['groupID' => $groupID]);
            if ($count == 0) {
                continue;
            }

            $groupName = $row['name'];
            $a = in_array(substr(strtolower($groupName), 0, 1), ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';
            $values = @$stats['groups'][$groupID];
            $level = static::getLevel(@$values['shipsDestroyed']);
            $levelCount += $level;
            $trophies['trophies']['Killed']["Kill $a $groupName"] = ['met' => (@$values['shipsDestroyed'] > 0), 'level' => $level, 'value' => (int) @$values['shipsDestroyed'], 'next' => static::getNext(@$values['shipsDestroyed']), 'link' => "/character/$charID/kills/reset/group/$groupID/losses/"];
            $levelCount += @$values['shipsDestroyed'] > 0;
            $level = static::getLevel(@$values['shipsLost']);
            $levelCount += $level;
            $trophies['trophies']['Lost']["Lose $a $groupName"] = ['met' => (@$values['shipsLost'] > 0), 'level' => $level, 'value' => (int) @$values['shipsLost'], 'next' => static::getNext(@$values['shipsLost']), 'link' => "/character/$charID/losses/group/$groupID/"];
            $maxLevelCount += 10;
        }

        $level = 0;
        $total = 1;
        do {
            $total = $total * 2;
            ++$level;
        } while ($total < $levelCount);
        $completed = number_format(($levelCount / $maxLevelCount) * 100, 0);

        $total = 0;
        $count = 0;
        foreach ($trophies['trophies'] as $more) {
            foreach ($more as $condition) {
                $total += @$condition['level'];
                ++$count;
            }
        }
        $levelAvg = floor($total / ($count * 5) * 10);

        $trophies['level'] = self::getLevel($levelCount);
        $trophies['levelAvg'] = $levelAvg;
        $trophies['levelCount'] = $levelCount;
        $trophies['maxLevelCount'] = $maxLevelCount;
        $trophies['nextLevel'] = pow(2, $level);
        $trophies['completedPct'] = $completed;

        return $trophies;
    }

    public static function addTrophy(&$trophies, $condition, $conditionMet, $value, $noNext = false, $link = null)
    {
        $level = static::getLevel($value);
        $arr = ['met' => $conditionMet, 'level' => $level, 'value' => $value, 'next' => static::getNext($value), 'noNext' => $noNext];
        if (isset($condition['link'])) {
            $arr['link'] = $condition['link'];
        }
        $trophies['trophies'][$condition['type']][$condition['name']] = $arr;

        return $conditionMet ? 1 : 0;
    }

    public static function getLevel($value)
    {
        $value = (int) $value;
        if ($value <= 0) {
            return 0;
        }
        if ($value < 5) {
            return 1;
        }
        if ($value < 25) {
            return 2;
        }
        if ($value < 125) {
            return 3;
        }
        if ($value < 625) {
            return 4;
        }

        return 5;
    }

    public static function getNext($value)
    {
        $value = (int) $value;
        if ($value == 0) {
            return 1;
        }
        if ($value < 5) {
            return 5;
        }
        if ($value < 25) {
            return 25;
        }
        if ($value < 125) {
            return 125;
        }
        if ($value < 625) {
            return 625;
        }

        return;
    }
}
