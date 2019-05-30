<?php


namespace Boss\task;


use Boss\entity\ZeusBoss;
use Boss\Loader;
use pocketmine\scheduler\Task;

class ZeusDeployTask extends Task
{

    /** @var ZeusBoss $zeus */
    private $zeusId;

    /**
     * ZeusDeployTask constructor.
     * @param int $zeusId
     */
    public function __construct(int $zeusId)
    {
        $this->zeusId = $zeusId;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick)
    {
        $found = false;
        foreach(Loader::$ins->getServer()->getLevels() as $level){
            $found = false;
            foreach($level->getEntities() as $e){
                if($e->getId() == $this->zeusId){
                    $e->revenge($e);
                    $found = true;
                }
            }
        }
        if(!$found){
            Loader::$ins->getScheduler()->cancelTask($this->getTaskId());
        }

    }

}