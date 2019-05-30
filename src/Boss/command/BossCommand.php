<?php


namespace Boss\command;


use Boss\entity\HumanBoss;
use Boss\entity\MobBoss;
use Boss\entity\ZeusBoss;
use Boss\Loader;
use muqsit\invmenu\inventories\BaseFakeInventory;
use muqsit\invmenu\InvMenu;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;

class BossCommand extends PluginCommand
{

    /**
     * BossCommand constructor.
     * @param Plugin $owner
     */
    public function __construct(Plugin $owner)
    {
        parent::__construct("boss", $owner);
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return bool|mixed
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if(!isset($args[0])){
            $this->sendHelp($sender);
            return true;
        }

        switch($args[0]){
            case "list";
            $bosses = array_values(Loader::EGG_LIST);
            $sender->sendMessage(TextFormat::GREEN . "Bosses: " . TextFormat::YELLOW . implode(" ", $bosses));
            break;
            case "give";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This can be executed only in game");
                return true;
            }

            $bosses = Loader::EGG_LIST;
            if(!in_array($args[1], array_values($bosses))){
                $sender->sendMessage(TextFormat::RED . "Invalid boss");
                return true;
            }

            $eggDamage = array_search($args[1], $bosses);
            $sender->getInventory()->addItem(Item::get(Item::SPAWN_EGG, $eggDamage, 1)->setCustomName($args[1] . 'Boss'));
            break;
            case "setspawn";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This can be executed only in game");
                return true;
            }

            if(!isset($args[1]) || !isset($args[2])){
                $sender->sendMessage(TextFormat::GREEN . "/boss setspawn " . TextFormat::YELLOW . "[amount] [hours|days|minutes|seconds]");
                return true;
            }

            $available = [
                'hours',
                'minutes',
                'days',
                'seconds'
            ];

            if(!in_array($args[2], $available)){
                $sender->sendMessage(TextFormat::GREEN . "Available: " . TextFormat::YELLOW . implode(" ", $available));
                return true;
            }

            $pos = $sender->getX() . ":" . $sender->getY() . ":" . $sender->getZ() . ":" . $sender->getLevel()->getFolderName();

            Loader::$ins->nextSpawn = strtotime('+ ' . intval($args[1]) . " " . $args[2]);
            $this->getPlugin()->dailySpawn->set('finish_time', strtotime('+ ' . intval($args[1]) . " " . $args[2]));
            $this->getPlugin()->dailySpawn->set('point_format', '+ ' . intval($args[1]) . " " . $args[2]);
            $this->getPlugin()->dailySpawn->set('position', $pos);
            $this->getPlugin()->dailySpawn->save();

            $sender->sendMessage(TextFormat::GREEN . "Boss spawn was set on " . $sender->getX() . ":" . $sender->getY() . ":" . $sender->getZ() . " every " . intval($args[1]) . " " . $args[2]);
            break;
            case "setproperty";

            if(!isset($args[1])){
                $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "Usage: /boss setproperty [bossName] [property|propertyGroup] [null|args...] [isBaby=false]");
                $sender->sendMessage(Loader::PREFIX . TextFormat::GOLD . "NOTE: " . TextFormat::GREEN . "'isBaby' argument work only for this properties: 'health => max', 'drops'");
                return true;
            }

            $array = array_map('strtolower', array_values(Loader::EGG_LIST));
            if(!in_array(strtolower($args[1]), $array)){
                $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "Boss called '{$args[1]}' not found");
                return true;
            }


            if(!isset($args[2])){
                a:
                $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "Available properties:");
                $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "health " . TextFormat::GREEN . "Health properties");
                $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "baby " . TextFormat::GREEN . "Baby boss properties");
                $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "drops " . TextFormat::GREEN . "Death drops property");
                return true;
            }

            switch(strtolower($args[2])){
                default:
                    goto a;
                    break;
                case "health";
                if(!isset($args[3])){
                    $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "Available properties for health: max, barLines");
                    return true;
                }

                switch(strtolower($args[3])){
                    case "max";
                    if(!isset($args[4])){
                        $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "No value given for property 'max' in group 'health' " . TextFormat::RED . '[Required: integer]');
                        return true;
                    }

                    if(!is_int(intval($args[4]))){
                        $sender->sendMessage(Loader::PREFIX . Loader::PREFIX . TextFormat::YELLOW . "Invalid given for property 'max' in group 'health' " . TextFormat::RED . '[Required: integer]');
                        return true;
                    }

                    $maxHealth = intval($args[4]);
                    $a = $this->getPlugin()->bossPropertyManager->get(strtolower($args[1]));
                    $isBaby = false;

                    if(isset($args[5])){
                            $isBaby = boolval($args[5]);
                    }

                    $a['health']['maxHealth_' . $isBaby ? "baby" : "adult"] = $maxHealth;
                    $this->getPlugin()->bossPropertyManager->set(strtolower($args[1]), $a);
                    $this->getPlugin()->bossPropertyManager->save();
                    break;
                    case "barLines";
                    if(!isset($args[4])){
                        $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "No value given for property 'barLines' in group 'health' " . TextFormat::RED . '[Required: integer]');
                        return true;
                    }

                    if(!is_int(intval($args[4]))){
                        $sender->sendMessage(Loader::PREFIX . Loader::PREFIX . TextFormat::YELLOW . "Invalid given for property 'barLines' in group 'health' " . TextFormat::RED . '[Required: integer]');
                        return true;
                    }

                    $barLines = intval($args[4]);
                    $a = $this->getPlugin()->bossPropertyManager->get(strtolower($args[1]));
                    $a['health']['barLines'] = $barLines;
                    $this->getPlugin()->bossPropertyManager->set(strtolower($args[1]), $a);
                    $this->getPlugin()->bossPropertyManager->save();
                    break;
                    case "useBar";
                    if(!isset($args[4])){
                        $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "No value given for property 'useBar' in group 'health' " . TextFormat::RED . '[Required: integer]');
                        return true;
                    }

                    if(!is_bool(intval($args[4]))){
                        $sender->sendMessage(Loader::PREFIX . Loader::PREFIX . TextFormat::YELLOW . "Invalid given for property 'userBar' in group 'health' " . TextFormat::RED . '[Required: boolean]');
                        return true;
                    }

                    $isBaby = false;

                    if(isset($args[5])){
                        $isBaby = boolval($args[5]);
                    }

                    $barLines = intval($args[4]);
                    $a = $this->getPlugin()->bossPropertyManager->get(strtolower($args[1]));
                    $a['health']['useBar_' . $isBaby ? 'baby' : 'adult'] = $barLines;
                    $this->getPlugin()->bossPropertyManager->set(strtolower($args[1]), $a);
                    $this->getPlugin()->bossPropertyManager->save();
                    break;
                }
                break;
                case "baby";
                if(!isset($args[3])){
                    $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "Available properties for health: spawn, amount");
                    return true;
                }

                switch(strtolower($args[3])){
                    case "spawn";
                    if(!isset($args[4])){
                        $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "No value given for property 'spawn' in group 'baby' " . TextFormat::RED . '[Required: boolean]');
                        return true;
                    }

                    if(!is_bool(boolval($args[4]))){
                        $sender->sendMessage(Loader::PREFIX . Loader::PREFIX . TextFormat::YELLOW . "Invalid given for property 'spawn' in group 'baby' " . TextFormat::RED . '[Required: boolean]');
                            return true;
                    }

                    $spawn = boolval($args[4]);
                    $a = $this->getPlugin()->bossPropertyManager->get(strtolower($args[1]));
                    $a['baby']['spawn'] = $spawn;
                    $this->getPlugin()->bossPropertyManager->set(strtolower($args[1]), $a);
                    $this->getPlugin()->bossPropertyManager->save();
                    break;
                    case "amount";
                    if(!isset($args[4])){
                        $sender->sendMessage(Loader::PREFIX . TextFormat::YELLOW . "No value given for property 'amount' in group 'baby' " . TextFormat::RED . '[Required: integer]');
                        return true;
                    }

                    if(!is_int(intval($args[4]))){
                        $sender->sendMessage(Loader::PREFIX . Loader::PREFIX . TextFormat::YELLOW . "Invalid given for property 'amount' in group 'baby' " . TextFormat::RED . '[Required: integer]');
                        return true;
                    }

                    $amount = intval($args[4]);
                    $a = $this->getPlugin()->bossPropertyManager->get(strtolower($args[1]));
                    $a['baby']['amount'] = $amount;
                    $this->getPlugin()->bossPropertyManager->set(strtolower($args[1]), $a);
                    $this->getPlugin()->bossPropertyManager->save();
                    break;
                }
                break;
                case "drops";
                if(!$sender instanceof Player){
                    return true;
                }

                $isBaby = false;

                if(isset($args[4])){
                    echo 'set to baby';
                    $isBaby = boolval($args[4]);
                }

                $menu = InvMenu::create(InvMenu::TYPE_CHEST);
                $inventory = $menu->getInventory();

                $inventory->clearAll();
                $inventory->setContents($this->getPlugin()->getDrops($args[1], $isBaby));
                $menu->setName("Put your drops here [" . ucfirst($args[1]) . "]");

                $menu->send($sender);

                Loader::$ins->updatedBossData = ['name' => $args[1], 'isBaby' => $isBaby];

                $menu->setInventoryCloseListener(function(Player $player, BaseFakeInventory $inventory) : bool{
                    $this->getPlugin()->updateDrops(Loader::$ins->updatedBossData['name'], Loader::$ins->updatedBossData['isBaby'], $inventory->getContents());
                    return false;
                });





                break;

            }

            break;
            case "info";
            $entities = 0;
            $zeus_adult = 0;
            $zeus_baby = 0;
            foreach($this->getPlugin()->getServer()->getLevels() as $level){
                foreach($level->getEntities() as $entity){
                    if($entity instanceof HumanBoss){
                        $entities++;
                        if($entity instanceof ZeusBoss){
                            $entity->isBaby ? $zeus_baby++ : $zeus_adult++;
                        }
                    }
                }
            }

            $sender->sendMessage(Loader::PREFIX . TextFormat::GREEN . "Total entities " . TextFormat::YELLOW . "(" . $entities . ")");
            $sender->sendMessage(Loader::PREFIX . TextFormat::GREEN . "Zeus: " . TextFormat::YELLOW . $zeus_adult . "|" . $zeus_baby);
            break;
        }
        return parent::execute($sender, $commandLabel, $args);
    }

    /**
     * @param CommandSender $sender
     */
    public function sendHelp(CommandSender $sender) : void{
        $sender->sendMessage(TextFormat::BOLD . TextFormat::RED . "BossSystem Commands");
        $sender->sendMessage(TextFormat::GRAY . "- " . TextFormat::YELLOW . "list " . TextFormat::GRAY . "List all bosses");
        $sender->sendMessage(TextFormat::GRAY . "- " . TextFormat::YELLOW . "give " . TextFormat::GRAY . "Give SpawnEgg of a boss");
        $sender->sendMessage(TextFormat::GRAY . "- " . TextFormat::YELLOW . "setspawn " . TextFormat::GRAY . "Set spawn rate/position of a boss");
        $sender->sendMessage(TextFormat::GRAY . "- " . TextFormat::YELLOW . "setproperty " . TextFormat::GRAY . "Update property of a boss");
        $sender->sendMessage(TextFormat::GRAY . "- " . TextFormat::YELLOW . "info " . TextFormat::GRAY . "Get info about spawned entities");

    }

}