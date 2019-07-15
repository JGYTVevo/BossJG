<?php


namespace Boss\entity;


use Boss\Loader;
use pocketmine\entity\Creature;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\Player;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\Server;
use pocketmine\utils\UUID;
use pocketmine\math\Vector2;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\block\Liquid;

class HumanBoss extends Human implements BossInterface
{

    /** @var bool $isBaby */
    public $isBaby = false;

    /**
     * @param Player $player
     */
    protected function sendSpawnPacket(Player $player) : void{

        $uuid = UUID::fromRandom();

        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_ADD;
        $pk->entries = [PlayerListEntry::createAdditionEntry($uuid, $this->id, 'Boss', $this->skin)];
        $player->dataPacket($pk);

        $pk = new AddPlayerPacket();
        $pk->uuid = $uuid;
        $pk->username = 'Boss';
        $pk->entityRuntimeId = $this->id;
        $pk->position = $this->asVector3();
        $pk->motion = $this->getMotion();
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->item = Item::get(Item::AIR);
        $pk->metadata = $this->propertyManager->getAll();
        $player->dataPacket($pk);
        $this->sendData($player, [self::DATA_NAMETAG => [self::DATA_TYPE_STRING, $this->getNameTag()]]);

        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_REMOVE;
        $pk->entries = [PlayerListEntry::createRemovalEntry($uuid)];
        $player->dataPacket($pk);

        $this->uuid = $uuid;
    }

    public function attack(EntityDamageEvent $source) : void{
        if($this->attackTime > 0 or $this->noDamageTicks > 0){
            $lastCause = $this->getLastDamageCause();
            if($lastCause !== null){
                $source->setCancelled();
                return;
            }
        }
        $this->setLastDamageCause($source);

        Entity::attack($source);

        if($source instanceof EntityDamageByEntityEvent){
            $e = $source->getDamager();
            $deltaX = $this->x - $e->x;
            $deltaZ = $this->z - $e->z;
            $yaw = atan2($deltaX, $deltaZ);
            $this->knockBack($e, $source->getFinalDamage(), sin($yaw), cos($yaw), $source->getKnockBack());
        }
        $pk = new ActorEventPacket();
        $pk->entityRuntimeId = $this->getId();
        $pk->event = $this->isAlive() ? 2 : 3;
        Server::getInstance()->broadcastPacket($this->level->getPlayers(), $pk);


        $this->attackTime = 10;
    }

    /**
     * @param int $tickDiff
     * @return bool
     */
    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if($this->attackTime > 0){
            $this->attackTime -= $tickDiff;
        }

        return parent::entityBaseTick($tickDiff);
    }



}
