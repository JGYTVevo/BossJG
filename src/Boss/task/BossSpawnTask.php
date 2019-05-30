<?php


namespace Boss\task;


use Boss\Loader;
use pocketmine\entity\Entity;
use pocketmine\level\Level;
use pocketmine\scheduler\Task;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;

class BossSpawnTask extends Task
{

    public function onRun(int $currentTick) : void{
        $plugin = Loader::$ins;

        if($plugin->nextSpawn == 0)return;

        $posString = Loader::$ins->dailySpawn->get('position');
        $posString = explode(":", $posString);

        $x = intval($posString[0]);
        $y = intval($posString[1]);
        $z = intval($posString[2]);
        $level = $posString[3];




        if(time() >= $plugin->nextSpawn){
            $plugin->dailySpawn->set('finish_time', $plugin->dailySpawn->get('point_format'));
            $plugin->dailySpawn->save();

            $plugin->nextSpawn = strtotime($plugin->dailySpawn->get('point_format'));

            $level = $plugin->getServer()->getLevelByName($level);
            if($level instanceof Level){

                $bossName = Loader::EGG_LIST[array_rand(Loader::EGG_LIST)];
                $level->loadChunk($x, $z);
                $nbt = Entity::createBaseNBT(new Vector3($x, $y + 1.5, $z));;
                $bossEntity = Entity::createEntity($bossName . "Boss", $level, $nbt);
                $bossEntity->spawnToAll();
                $bossEntity->setAnAdult();

                $plugin->getServer()->broadcastMessage(TextFormat::AQUA . "Boss has been spawned!");

            }
        }else{
            $remainTime = $plugin->nextSpawn - time();
            $hour = ($remainTime / 3600);
            $min = ($remainTime / 60);

            if($hour <= 24 && $hour !== 0){
                if($min == 60 || $min == 0){
                    $plugin->getServer()->broadcastMessage(TextFormat::AQUA . "Boss will spawn in " . TextFormat::YELLOW . $hour . " " . TextFormat::AQUA . "hour(s)");
                    return;
                }
            }

            if($remainTime >= 60) {
                switch ($min) {
                    case 30;
                    case 20;
                    case 10;
                    case 5;
                    case 1;
                        $plugin->getServer()->broadcastMessage(TextFormat::AQUA . "Boss will spawn in " . TextFormat::YELLOW . $min . " " . TextFormat::AQUA . "minute(s)");
                        break;
                }
            }else{
                switch($remainTime){
                    case 20;
                    case 10;
                    case 5;
                    case 4;
                    case 3;
                    case 2;
                    case 1;
                    $plugin->getServer()->broadcastMessage(TextFormat::AQUA . "Boss will spawn in " . TextFormat::YELLOW . $remainTime . " " . TextFormat::AQUA . "second(s)");
                    break;
                }
            }



        }
    }

}