<?php

class Related
{
    private static $killstorage = array();

    public static function buildSummary(&$kills, $options)
    {
        $timer = new Timer();
        $involvedEntities = array();
        foreach ($kills as $killID => $kill) {
            self::addAllInvolved($involvedEntities, $killID, $kill);
        }

        list($redTeam, $blueTeam) = self::createTeams($kills);

        if (isset($options['A'])) {
            self::assignSides($options['A'], $redTeam, $blueTeam);
        }
        if (isset($options['B'])) {
            self::assignSides($options['B'], $blueTeam, $redTeam);
        }

        $redInvolved = self::getInvolved($kills, $redTeam);
        $blueInvolved = self::getInvolved($kills, $blueTeam);

        $redKills = self::getKills($kills, $redTeam);
        $blueKills = self::getKills($kills, $blueTeam);

        self::addMoreInvolved($redInvolved, $redKills);
        self::addMoreInvolved($blueInvolved, $blueKills);
        Info::addInfo($redInvolved);
        Info::addInfo($blueInvolved);

        $redTotals = self::getStatsKillList(array_keys($redKills));
        $redTotals['pilotCount'] = sizeof($redInvolved);
        $blueTotals = self::getStatsKillList(array_keys($blueKills));
        $blueTotals['pilotCount'] = sizeof($blueInvolved);

        $red = self::addInfo($redTeam);
        asort($red);
        $blue = self::addInfo($blueTeam);
        asort($blue);

        usort($redInvolved, 'Related::compareShips');
        usort($blueInvolved, 'Related::compareShips');

        $retValue = array(
                'teamA' => array(
                    'list' => $redInvolved,
                    'kills' => $redKills,
                    'totals' => $redTotals,
                    'entities' => $red,
                    ),
                'teamB' => array(
                    'list' => $blueInvolved,
                    'kills' => $blueKills,
                    'totals' => $blueTotals,
                    'entities' => $blue,
                    ),
                );

        return $retValue;
    }

    private static function addAllInvolved(&$entities, $killID, $kill)
    {
        self::$killstorage[$killID] = $kill;

        $victim = $kill['involved'][0];
        self::addInvolved($entities, $victim);
        $involved = $kill['involved'];
        array_shift($involved);
        if (is_array($involved)) {
            foreach ($involved as $entry) {
                self::addInvolved($entities, $entry);
            }
        }
    }

    private static function addInvolved(&$entities, &$entry)
    {
        $entity = isset($entry['allianceID']) && $entry['allianceID'] != 0 ? $entry['allianceID'] : @$entry['corporationID'];
        if ($entity == 0) {
            return;
        }
        if (!isset($entities["$entity"])) {
            $entities["$entity"] = array();
        }
        if (!in_array(@$entry['characterID'], $entities["$entity"])) {
            $entities["$entity"][] = @$entry['characterID'];
        }
    }

    private static function getInvolved(&$kills, $team)
    {
        $involved = array();
        foreach ($kills as $kill) {
            $kill = self::$killstorage[$kill['victim']['killID']];

            $attackers = $kill['involved'];
            array_shift($attackers);
            if (is_array($attackers)) {
                foreach ($attackers as $entry) {
                    $add = false;
                    if (@$entry['allianceID'] != 0 && in_array(@$entry['allianceID'], $team)) {
                        $add = true;
                    }
                    if (@$entry['corporationID'] != 0 && in_array(@$entry['corporationID'], $team)) {
                        $add = true;
                    }

                    if ($add) {
                        $key = @$entry['characterID'].':'.@$entry['corporationID'].':'.@$entry['allianceID'].':'.@$entry['shipTypeID'];
                        if (!isset($entry['shipName'])) {
                        $entry['shipName'] = Info::getInfoField('typeID', @$entry['shipTypeID'], 'name');
                        }
                        if (!in_array($key, $involved)) {
                            $involved[$key] = $entry;
                        }
                    }
                }
            }
        }

        return $involved;
    }

    private static function addMoreInvolved(&$team, $kills)
    {
        foreach ($kills as $kill) {
            $victim = $kill['victim'];
            Info::addInfo($victim);
            if (@$victim['characterID'] > 0 && @$victim['groupID'] != 29) {
                $key = @$victim['characterID'].':'.@$victim['corporationID'].':'.@$victim['allianceID'].':'.$victim['shipTypeID'];
                $victim['destroyed'] = $victim['killID'];
                $team[$key] = $victim;
            }
        }
    }

    private static function getKills(&$kills, $team)
    {
        $teamsKills = array();
        foreach ($kills as $killID => $kill) {
            $victim = $kill['victim'];
            $add = in_array((int) @$victim['allianceID'], $team) || in_array($victim['corporationID'], $team);

            if ($add) {
                $teamsKills[$killID] = $kill;
            }
        }

        return $teamsKills;
    }

    private static function getStatsKillList($killIDs)
    {
        $totalPrice = 0;
        $totalPoints = 0;
        $groupIDs = array();
        $totalShips = 0;
        foreach ($killIDs as $killID) {
            $kill = self::$killstorage[$killID];
            $victim = $kill['victim'];
            $totalPrice += $kill['zkb']['totalValue'];
            $totalPoints += $kill['zkb']['points'];
            $groupID = $victim['groupID'];
            if (!isset($groupIDs[$groupID])) {
                $groupIDs[$groupID] = array();
                $groupIDs[$groupID]['count'] = 0;
                $groupIDs[$groupID]['isk'] = 0;
                $groupIDs[$groupID]['points'] = 0;
                $groupIDs[$groupID]['fielded'] = [];
            }
            $groupIDs[$groupID]['groupID'] = $groupID;
            ++$groupIDs[$groupID]['count'];
            $groupIDs[$groupID]['isk'] += $kill['zkb']['totalValue'];
            $groupIDs[$groupID]['points'] += $kill['zkb']['points'];
            ++$totalShips;
            foreach ($kill['involved'] as $involved) {
                $charID = @$involved['characterID'];
                $groupID = @$involved['groupID'];
                if ($charID == 0 || $groupID == 0) continue;
                if (!isset($groupIDs[$groupID])) {
                    $groupIDs[$groupID] = array();
                    $groupIDs[$groupID]['groupID'] = $groupID;
                    $groupIDs[$groupID]['count'] = 0;
                    $groupIDs[$groupID]['isk'] = 0;
                    $groupIDs[$groupID]['points'] = 0;
                    $groupIDs[$groupID]['fielded'] = [];
                }
                $groupIDs[$groupID]['fielded']["$charID:$groupID"] = true;
            }
        }
        Info::addInfo($groupIDs);

        return array(
                'total_price' => $totalPrice, 'groupIDs' => $groupIDs, 'totalShips' => $totalShips,
                'total_points' => $totalPoints,
                );
    }

    private static function addInfo(&$team)
    {
        $retValue = array();
        foreach ($team as $entity) {
            if (isset($retValue[$entity])) continue;
            $name = Info::getInfoField('allianceID', $entity, 'name');
            if ($name === null) $name = Info::getInfoField('corporationID', $entity, 'name');
            if ($name === null) $name = "Entity $entity";
            $retValue[$entity] = $name;
        }

        return $retValue;
    }

    /**
     * Take all involved parties from the kills and divide them into 2 groups based on who shot who.
     *
     * @param array $kills
     *
     * @return array
     */
    private static function createTeams($kills)
    {
        $score = [];
        $entities = [];
        foreach ($kills as $kill) {
            $victim = $kill['victim'];
            $entities[self::determineEntityId($victim)] = $victim;

            foreach ($kill['involved'] as $involved) {
                $entities[self::determineEntityId($involved)] = $involved;
                self::addScore($victim, $involved, $score);
            }
        }
        $entities = self::sortEntitiesByLargestGroup($entities);

        // Calculate who hates who
        $teams = [
            'red' => [],
            'blue' => [],
            ];
        foreach ($entities as $entity) {
            $affiliationId = self::determineAffiliationId($entity);
            if (is_null($affiliationId)) {
                continue;
            }

            if (self::calcScore($entity, $score, $teams['red']) <= self::calcScore($entity, $score, $teams['blue'])) {
                $teams['red'][$affiliationId] = $entity;
            } else {
                $teams['blue'][$affiliationId] = $entity;
            }
        }

        // Distill sorted involved parties into their most broad affiliation.
        $groups = [];
        foreach ($teams as $teamName => $team) {
            $groups[$teamName] = [];
            foreach ($team as $entity) {
                $groups[$teamName][self::determineAffiliationId($entity)] = self::determineAffiliationId($entity);
            }
        }

        return array_values($groups);
    }

    /**
     * Returns the Id of the most broad affiliation of the given entity.
     * This can be a corporationID or allianceID.
     *
     * @param array $entity
     *
     * @return null|int
     */
    private static function determineAffiliationId($entity)
    {
        foreach (array('allianceID', 'corporationID') as $possibleId) {
            if (isset($entity[$possibleId])) {
                return $entity[$possibleId];
            }
        }

        return;
    }

    /**
     * Return the most specific identifier for an entity.
     * This can be characterID, corporationID or allianceID.
     *
     * @param array $entity
     */
    private static function determineEntityId($entity)
    {
        foreach (array('characterID', 'corporationID', 'allianceID') as $possibleId) {
            if (isset($entity[$possibleId])) {
                return $entity[$possibleId];
            }
        }

        return;
    }

    /**
     * Add a hate score between two entities for each stage of uniqueness and working both ways.
     *
     * The score list runs both ways and cross affiliation, so corp X can hate char Y for shooting
     * the affiliated pilots, and char Z can hate all pilots from alliance X for shooting him.
     *
     * $score = array(
     *      'victim.characterID' => array(
     *          'involved.characterID' => 5,
     *          'involved.corporationID' => 5,
     *          'involved.allianceID' => 5
     *      ),
     *      'victim.corporationID' => array(
     *          ...
     *      ),
     *      'victim.allianceID' => array(
     *          ...
     *      ),
     *      'involved.characterID' => array(
     *      ...
     *      ...
     * )
     *
     * @param array $victim
     * @param array $involved
     * @param array $score
     */
    private static function addScore($victim, $involved, &$score)
    {
        foreach (array('characterID', 'corporationID', 'allianceID') as $type) {
            foreach (array('characterID', 'corporationID', 'allianceID') as $type2) {
                if (isset($victim[$type]) && isset($involved[$type2])) {
                    $victimId = $victim[$type];
                    $involvedId = $involved[$type2];

                    if (!isset($score[$victimId])) {
                        $score[$victimId] = [];
                    }
                    if (!isset($score[$involvedId])) {
                        $score[$involvedId] = [];
                    }
                    if (!isset($score[$victimId][$involvedId])) {
                        $score[$victimId][$involvedId] = 0;
                    }
                    if (!isset($score[$involvedId][$victimId])) {
                        $score[$involvedId][$victimId] = 0;
                    }

                    $score[$victimId][$involvedId] += 5;
                    $score[$involvedId][$victimId] = &$score[$victimId][$involvedId];
                }
            }
        }
    }

    /**
     * Aggregate the combined hate score based on the members in a given team vs the given entity.
     * Scoring between entities belonging to the same group are ignored. Bads shoot each other too much.
     *
     * @param array $entity
     * @param array $scoreList
     * @param array $team
     *
     * @return int
     */
    private static function calcScore($entity, $scoreList, $team)
    {
        $score = 0;
        foreach ($team as $memberEntity) {
            foreach (array('characterID', 'corporationID', 'allianceID') as $typeId) {
                foreach (array('characterID', 'corporationID', 'allianceID') as $typeId2) {
                    if (isset($entity[$typeId]) && isset($memberEntity[$typeId2])) {
                        if ($entity[$typeId] == $memberEntity[$typeId2]) {
                            return -50;
                        }

                        // If we have beef, apply the score
                        if (isset($scoreList[$entity[$typeId]][$memberEntity[$typeId2]])) {
                            $score += $scoreList[$entity[$typeId]][$memberEntity[$typeId2]];
                        }
                    }
                }
            }
        }

        return $score;
    }

    private static function assignSides($assignees, &$teamA, &$teamB)
    {
        foreach ($assignees as $id) {
            if (!isset($teamA[$id])) {
                $teamA[] = $id;
            }
            if (($key = array_search($id, $teamB)) !== false) {
                unset($teamB[$key]);
            }
        }
    }

    public static $masses = [];

    public static function getMass($typeID)
    {
        if (!isset(self::$masses[$typeID])) {
            $mass = (int) Info::getInfoField('typeID', $typeID, 'mass');
            self::$masses[$typeID] = $mass;
        }

        return self::$masses[$typeID];
    }

    public static function compareShips($a, $b)
    {
        $aTypeID = @$a['shipTypeID'];
        $bTypeID = @$b['shipTypeID'];
        
        $aSize = self::getMass($aTypeID);
        $bSize = self::getMass($bTypeID);
        
        if ($aSize == $bSize) {
            return $aTypeID < $bTypeID;
        }

        return $aSize < $bSize;
    }

    /**
     * find the largest "Blocks" and sort their members to the top
     * There really must be a more elegant way to do this shit.
     *
     * @param array $entities
     *
     * @return array
     */
    private static function sortEntitiesByLargestGroup($entities)
    {
        $sortArray = [];
        $sortOrder = [];
        foreach ($entities as $key => $entity) {
            $groupId = self::determineAffiliationId($entity);
            if (!isset($sortArray[self::determineAffiliationId($entity)])) {
                $sortArray[$groupId] = 1;
            } else {
                $sortArray[$groupId] += 1;
            }
            $sortOrder[$key] = &$sortArray[$groupId];
        }
        array_multisort($sortOrder, SORT_DESC, $entities);

        return $entities;
    }
}
