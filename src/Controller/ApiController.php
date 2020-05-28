<?php

namespace App\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiController extends AbstractController
{


    private $key = "RGAPI-4de7aefa-38e6-4cec-b9a8-47ebe21e18e2";
    public $version = "10.9.1";

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    //recuperer la BDD des champions
    public function getData()
    {
        $data = $this->httpClient->request('GET', "http://ddragon.leagueoflegends.com/cdn/$this->version/data/en_US/champion.json");
        return json_decode($data->getContent(), true);
    }

    //recuperer les champions gratuit de la semaine

    public function getRotation()
    {
        $response = $this->httpClient->request('GET', "https://euw1.api.riotgames.com/lol/platform/v3/champion-rotations?api_key=$this->key");

        return json_decode($response->getContent(), true);
    }
    /*  
    Recuperer : {
        "id":"jrzKIBkwiDeH8kT7cy63XJyL3QlI_Kd0GDQUB1jd64EtREk",
        "accountId":"IN8g-Zkt7E_J5dDOGY610aw--jJaVbd5Jl-Fek6-i7TCpQ",
        "puuid":"GUYD41dFv5RQ6yRZfzPPJ60BXD8-W7hjNm63GVz9CAtbeYnwAwKQIqr203xn2wbnjZMX8oXPdSebXA",
        "name":"DamDamDeoh",
        "profileIconId":578,
        "revisionDate":1588197687000,
        "summonerLevel":70}
    */
    public function getSummonerInfoBySummonerName($name)
    {
        $response = $this->httpClient->request('GET', "https://euw1.api.riotgames.com/lol/summoner/v4/summoners/by-name/$name?api_key=$this->key");
        return json_decode($response->getContent(), true);
    }

    public function getMatchList($accountId)
    {
        $response = $this->httpClient->request('GET', "https://euw1.api.riotgames.com/lol/match/v4/matchlists/by-account/$accountId?api_key=$this->key");

        return json_decode($response->getContent(), true);
    }

    public function getStatsMatch($idMatch)
    {
        $response = $this->httpClient->request('GET', "https://euw1.api.riotgames.com/lol/match/v4/matches/$idMatch?api_key=$this->key");

        return json_decode($response->getContent(), true);
    }


    public function getTimelineByIdMatch($idMatch)
    {
        $response = $this->httpClient->request('GET', "https://euw1.api.riotgames.com/lol/match/v4/timelines/by-match/$idMatch?api_key=$this->key");

        return json_decode($response->getContent(), true);
    }

    public function getMatchlistBySummonerName($name)
    {
        $accountInfo = $this->getSummonerInfoBySummonerName($name);
        $accountId  = $accountInfo['accountId'];
        return $this->getMatchList($accountId);
    }

    public function getChampionById($id, $championsdata)
    {
        $championsdata = array_filter($championsdata, "is_array");
        foreach ($championsdata['data'] as $champion) {
            if ($champion['key'] == $id)
                return $champion;
        }
    }

    public function getPlayersStatsMatch($statsMatch)
    {
        foreach ($statsMatch["participants"] as $participant) {
            $players[] = [
                "participantId" => $participant["participantId"],
                "teamId" => $participant["teamId"],
                "championId" => $participant["championId"],
                "items" => [
                    $participant["stats"]["item0"],
                    $participant["stats"]["item1"],
                    $participant["stats"]["item2"],
                    $participant["stats"]["item3"],
                    $participant["stats"]["item4"],
                    $participant["stats"]["item5"],
                    $participant["stats"]["item6"],
                ],
                "kills" => $participant["stats"]["kills"],
                "deaths" => $participant["stats"]["deaths"],
                "assists" => $participant["stats"]["assists"],
            ];
        }

        $playersIdentities = $statsMatch["participantIdentities"];

        $championsData = $this->getData();
        foreach ($players as $player) {
            foreach ($playersIdentities as $playerIdentities) {
                if ($player["participantId"] == $playerIdentities["participantId"]) {
                    $champion = $this->getChampionById($player["championId"], $championsData);
                    $player["summonerName"] = $playerIdentities["player"]["summonerName"];
                    $player["championName"] = $champion["id"];
                    $playersResumeStats[] = $player;
                }
            }
        }

        return $playersResumeStats;
    }

    public function getWinner($statsMatch)
    {
        foreach ($statsMatch["teams"] as $team) {
            if ($team["win"] == "Win") {
                $winner = $team["teamId"];
            } else if ($team["win"] == "Fail") {
                $loser = $team["teamId"];
            }
        }

        $result = [
            "winner" => $winner,
            "loser" => $loser,
        ];

        return $result;
    }

    public function get10LastMatchsOfSummoner($matchList)
    {
        $i = 0;
        foreach ($matchList["matches"] as $match) {
            $matchIdList[] = $match["gameId"];
            $i++;
            if ($i == 10)
                break;
        }
        return $matchIdList;
    }

    public function getIdParticipant($summonerName, $statsMatch)
    {
        foreach ($statsMatch["participantIdentities"] as $participant) {
            if ($participant["player"]["summonerName"] == $summonerName) {
                $idParticipant = $participant["participantId"];
            }
        }
        return $idParticipant;
    }

    public function getPlayerTeamId($participantId, $statsMatch)
    {
        foreach ($statsMatch['participants'] as $participant) {
            if ($participantId == $participant['participantId']) {
                $participantTeamId = $participant['teamId'];
            }
        }

        return $participantTeamId;
    }

    public function getWinByTeamId($participantTeamId, $statsMatch)
    {
        foreach ($statsMatch['teams'] as $team)
            if ($team['teamId'] == $participantTeamId) {
                $win = $team['win'];
            }

        return $win;
    }

    public function getPlayerWin($summonerName, $statsMatch)
    {
        $participantId = $this->getIdParticipant($summonerName, $statsMatch);
        $participantTeamId = $this->getPlayerTeamId($participantId, $statsMatch);
        $win = $this->getWinByTeamId($participantTeamId, $statsMatch);

        return $win;
    }

    public function getStatsPlayerInMatch($summonerName, $statsMatch)
    {
        $participantId = $this->getIdParticipant($summonerName, $statsMatch);
        $playerStats['win'] = $this->getPlayerWin($summonerName, $statsMatch);
        $playerStats['matchId'] = $statsMatch["gameId"];
        foreach ($statsMatch["participants"] as $participant) {
            if ($participantId == $participant["participantId"]) {
                $playerStats["teamId"] = $participant["teamId"];
                $playerStats["championId"] = $participant["championId"];
                $playerStats["items"] = [
                    $participant["stats"]["item0"],
                    $participant["stats"]["item1"],
                    $participant["stats"]["item2"],
                    $participant["stats"]["item3"],
                    $participant["stats"]["item4"],
                    $participant["stats"]["item5"],
                    $participant["stats"]["item6"],
                ];
                $playerStats["kills"] = $participant["stats"]["kills"];
                $playerStats["deaths"] = $participant["stats"]["deaths"];
                $playerStats["assists"] = $participant["stats"]["assists"];
            }
        }

        return $playerStats;
    }

    public function getparticipantsIdInTimeline($timeLineMatch)
    {
        foreach ($timeLineMatch["frames"] as $participantFrame) {
            foreach ($participantFrame["participantFrames"] as $participant) {
                $participants[] = $participant['participantId'];
            }
            break;
        }
        return $participants;
    }

    public function getDataTimeLine($timeLineMatch, $dataOfTimeLine1, $dataOfTimeLine2 = false)
    {
        $table = [
            ['min']
        ];

        foreach ($timeLineMatch['frames'] as $frame) {
            foreach ($frame['participantFrames'] as $participant) {
                $table[0][] = $participant['participantId'];
            }
            break;
        }
        $i = 1;
        foreach ($timeLineMatch['frames'] as $frame) {
            $table[$i][] = $i;
            foreach ($frame['participantFrames'] as $participant) {
                if ($dataOfTimeLine2 != false)
                    $table[$i][] = $participant[$dataOfTimeLine1] + $participant[$dataOfTimeLine2];
                else
                    $table[$i][] = $participant[$dataOfTimeLine1];
            }
            $i++;
        }
        return ($table);
    }

    public function getSummonerNameByParticipant($statsMatch, $participantId)
    {
        foreach ($statsMatch["particiapntIdentities"] as $participant) {
            if ($participant['participantId'] === $participantId) {
                return $participant['player']["summonerName"];
            }
        }
    }

    public function getChampionByParticipant($statsMatch, $participantId, $championData)
    {
        foreach ($statsMatch["participants"] as $participant) {
            if ($participant['participantId'] === $participantId) {
                return $this->getChampionById($participant['championId'], $championData)['id'];
            }
        }
    }

    public function getDataByParticipantId($table, $participantId, $statsMatch, $championData)
    {
        $key = array_search($participantId, $table[0]);
        foreach ($table as $frame) {
            $participantTable[] = [$frame[0], $frame[$key]];
        }

        $participantTable[0][1] = $this->getChampionByParticipant($statsMatch, $participantTable[0][1], $championData);

        return ($participantTable);
    }

    public function formatTableau($playersCS, $championData, $statsMatch)
    {
        $playertab = ['min'];
        foreach ($playersCS[0] as $player) {
            if (is_numeric($player)) {
                $playertab[] = $this->getChampionByParticipant($statsMatch, $player, $championData);
            }
        }
        $playersCS[0] = array_replace($playersCS[0], $playertab);
        return $playersCS;
    }

    /**
     * @Route("/api", name="api")
     */
    public function index()
    {
        try {
            $data = $this->getData();
        } catch (Exception $e) {
            return $this->render('/bundles/TwigBundle/Exception/error' . $e->getCode() . '.html.twig', []);
        }

        try {
            $rotation = $this->getRotation();
        } catch (Exception $e) {
            return $this->render('/bundles/TwigBundle/Exception/error' . $e->getCode() . '.html.twig', []);
        }

        foreach ($rotation as $name => $key["freeChampionIds"]) {
            if (is_array($key)) {
                if ($name == "freeChampionIds")
                    foreach ($key as $id) {
                        $tableChampions[] = $id;
                    }
            }
        }

        foreach ($data as $champions) {
            if (is_array($champions)) {
                foreach ($champions as $champion) {
                    foreach ($tableChampions as $table) {
                        foreach ($table as $key) {
                            if ($key == $champion['key']) {
                                $tableNameFreeChampions[] = [$champion["id"], $champion["key"]];
                            }
                        }
                    }
                }
            }
        }

        return $this->render('api/index.html.twig', [
            'controller_name' => 'ApiController',
            'tableNamefreeChampions' => $tableNameFreeChampions,
        ]);
    }

    /**
     * @Route("/show/{id}", name="show")
     */
    public function show($id)
    {

        try {
            $data = $this->getData();
        } catch (Exception $e) {
            return $this->render('/bundles/TwigBundle/Exception/error' . $e->getCode() . '.html.twig', []);
        }

        try {
            $champion = $this->getChampionById($id, $data);
        } catch (Exception $e) {
            return $this->render('/bundles/TwigBundle/Exception/error' . $e->getCode() . '.html.twig', []);
        }

        return $this->render('champions/show.html.twig', [
            'championName' => $champion['name'],
            'championTitle' => $champion['title'],
            'championBlurb' => $champion['blurb'],
            'championImg' => $champion['image']['full'],
        ]);
    }

    /**
     * @Route("/history/{name}", name="history")
     */
    public function history($name, Request $request)
    {
        if ($name == -1) {
            return $this->redirectToRoute('history', ['name' => $request->query->get('name')]);
        }

        try {
            $matchList = $this->getMatchlistBySummonerName($name);
        } catch (Exception $e) {
            return $this->render('/bundles/TwigBundle/Exception/error' . $e->getCode() . '.html.twig', []);
        }

        try {
            $matchIdList = $this->get10LastMatchsOfSummoner($matchList);
        } catch (Exception $e) {
            return $this->render('/bundles/TwigBundle/Exception/error' . $e->getCode() . '.html.twig', []);
        }

        try {
            $championData = $this->getData();
        } catch (Exception $e) {
            return $this->render('/bundles/TwigBundle/Exception/error' . $e->getCode() . '.html.twig', []);
        }

        foreach ($matchIdList as $idMatch) {
            $statsMatch = $this->getStatsMatch($idMatch);
            $playerStatsMatchs[] = $this->getStatsPlayerInMatch($name, $statsMatch);
        }

        foreach ($playerStatsMatchs as $playerStats) {
            $champion = $this->getChampionById($playerStats["championId"], $championData);
            $playerStats["championName"] = $champion['id'];
            $playerHistory[] = $playerStats;
        }



        return $this->render('history/history.html.twig', [
            "playerHistory" => $playerHistory,
            "name" => $name,
            "version" => $this->version,
            'summonerLevel' => $this->getSummonerInfoBySummonerName($name)["summonerLevel"],
            "profileiconid" => $this->getSummonerInfoBySummonerName($name)["profileIconId"],
        ]);
    }


    /**
     * @Route("/match/{id}?={p}", name="match")
     */
    public function match($id, $p)
    {
        $data = $this->getData();
        $statsMatch = $this->getStatsMatch($id);
        $playersMatch = $this->getPlayersStatsMatch($statsMatch);
        $timeLineMatch = $this->getTimelineByIdMatch($id);
        $winner = $this->getWinner($statsMatch);

        $playersCS = $this->getDataTimeLine($timeLineMatch, 'minionsKilled', 'jungleMinionsKilled');
        $playersTotalGold = $this->getDataTimeLine($timeLineMatch, 'totalGold');
        $playersLevel = $this->getDataTimeLine($timeLineMatch, 'level');

        $playerCSParticipantId = $this->getDataByParticipantId($playersCS, $p, $statsMatch, $data);
        $playerGoldParticipantId = $this->getDataByParticipantId($playersTotalGold, $p, $statsMatch, $data);
        $playerLevelParticipantId = $this->getDataByParticipantId($playersLevel, $p, $statsMatch, $data);

        $playersCS = $this->formatTableau($playersCS, $data, $statsMatch);
        $playersTotalGold = $this->formatTableau($playersTotalGold, $data, $statsMatch);
        $playersLevel = $this->formatTableau($playersLevel, $data, $statsMatch);

        return $this->render('match/match.html.twig', [
            "matchId" => $id,
            "playersMatch" => $playersMatch,
            "winner" => $winner,
            "version" => $this->version,


            "playersCS" => $playersCS,
            "playersTotalGols" => $playersTotalGold,
            "playersLevel" => $playersLevel,

            "participantCS" => $playerCSParticipantId,
            "participantGold" => $playerGoldParticipantId,
            "participantLevel" => $playerLevelParticipantId,
        ]);
    }

    /**
     * @Route("/home", name="home")
     */
    public function home()
    {
        return $this->render('home/home.html.twig', []);
    }
}
