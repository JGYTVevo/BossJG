<?php


namespace Boss;


use Boss\command\BossCommand;
use Boss\entity\BossInterface;
use Boss\entity\HumanBoss;
use Boss\entity\MobBoss;
use Boss\entity\ZeusBoss;
use Boss\task\BossSpawnTask;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\SpawnEgg;
use pocketmine\plugin\PluginBase;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Loader extends PluginBase implements Listener
{

    const PREFIX = TextFormat::AQUA . "[" . TextFormat::RED . "Boss Manager" . TextFormat::AQUA . "] " . TextFormat::RESET;

    /** @var array $registredEntities */
    private $registredEntities = [];

    /** @var Loader $ins */
    public static $ins;

    /** @var Config $dailySpawn */
    public $dailySpawn;

    /** @var Config $bossPropertyManager */
    public $bossPropertyManager;

    /** @var int $nextSpawn */
    public $nextSpawn;

    /** @var array $bossProperties */
    public $bossProperties = [];

    /** @var array $dropSetup */
    public $dropSetup = [];

    /** @var array $updatedBossData */
    public $updatedBossData = [];

    const EGG_LIST = [
        110 => 'Zeus'
    ];

    public function onEnable() : void
    {
        self::$ins = $this;


        //init files
        @mkdir($this->getDataFolder() . "skins");
        $this->saveResource("skins/zeus.png");
        $this->saveResource("skins/small_zeus.png");
        $this->saveDefaultConfig();
        $this->dailySpawn = new Config($this->getDataFolder() . 'daily_spawn.json', Config::JSON, [
            'finish_time' => 0,
            'point_format' => 0,
            'position' => ""
        ]);
        $this->nextSpawn = $this->dailySpawn->get('finish_time');
        $this->bossPropertyManager = new Config($this->getDataFolder() . "boss_properties.json", Config::JSON);


        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getCommandMap()->register('boss', new BossCommand($this));
        $this->getScheduler()->scheduleRepeatingTask(new BossSpawnTask(), 20);

        $this->loadProperties();

        //init entities
        Entity::registerEntity(ZeusBoss::class, true);


    }

    public function loadProperties() : void{
        foreach(array_values(self::EGG_LIST) as $bossName){
            if(!$this->bossPropertyManager->exists(strtolower($bossName))){
                $this->bossPropertyManager->set(strtolower($bossName), [
                    'health' => [
                        'maxHealth_adult' => 100,
                        'healthBarLines_adult' => 2,
                        'healthPerBarLine_adult' => 1,
                        'maxHealth_baby' => 50,
                        'useBar_baby' => false,
                        'useBar_adult' => true
                    ],
                    'drops' => [
                        'baby' => [['id' => Item::DIAMOND, 'damage' => 0, 'count' => 64, 'enchantments' => [], 'tags' => ['name' => '', 'lore' => '']],
                        ['id' => Item::IRON_INGOT, 'damage' => 0, 'count' => 64, 'enchantments' => [], 'tags' => ['name' => '', 'lore' => '']]],
                        'adult' => [['id' => Item::DIAMOND, 'damage' => 0, 'count' => 64, 'enchantments' => [], 'tags' => ['name' => '', 'lore' => '']],
                        ['id' => Item::IRON_INGOT, 'damage' => 0, 'count' => 64, 'enchantments' => [], 'tags' => ['name' => '', 'lore' => '']]]
                    ],
                    'baby' => ['spawn' => false, 'amount' => rand(1,5)]
                ]);
                $this->bossPropertyManager->save();
            }

            $this->bossProperties[strtolower($bossName)] = $this->bossPropertyManager->get(strtolower($bossName));


        }
    }

    /**
     * @param string $bossName
     * @param string $property
     * @param string|null $group
     * @return mixed
     */
    public function getProperty(string $bossName, string $property, string $group = null){
        $a = $this->bossProperties[strtolower($bossName)];
        if($group == null){
            return $a[$property];
        }else{
            return $a[$group][$property];
        }
    }

    /**
     * @param array $entities
     */
    public function sendProperties($entities = []){
        if(empty($entities)){
            foreach($this->getServer()->getLevels() as $level){
                foreach($level->getEntities() as $entity){
                    if($entity instanceof HumanBoss || $entity instanceof MobBoss){
                        $entities[] = $entity;
                    }
                }
            }
        }

        foreach($entities as $entity){
            $propertyIndex = strtolower($entity->getName());
            $properties = $this->bossProperties[$propertyIndex];

            $maxHealth = $properties['health']['maxHealth_' . $entity->isBaby ? 'baby' : 'adult'];
            $entity->setMaxHealth($maxHealth);
        }


    }

    /**
     * @param string $group
     * @param string $propertyName
     * @param string $bossName
     * @param $value
     * @param $valueType
     *
     * TODO: replace it in command
     */
    public function updateProperty(string $group, string $propertyName, string $bossName, $value, $valueType){

    }

    /**
     * @param string $bossName
     * @param bool $isBab
     * @param array $items
     */
    public function updateDrops(string $bossName, bool $isBaby, array $items) : void{
         $defaultArray = $this->bossProperties[strtolower($bossName)];
         $array = [];
         foreach($items as $item){
             $array[] = ['id' => $item->getId(), 'damage' => $item->getDamage(), 'count' => $item->getCount(), 'enchantments' => [], 'tags' => ['name' => $item->getCustomName(), 'lore' => empty($item->getLore()) ? "" : implode("{LINE}", $item->getLore())]];
         }


         $defaultArray['drops'][$isBaby ? 'baby' : 'adult'] = $array;
         $this->bossPropertyManager->set(strtolower($bossName), $defaultArray);
         $this->bossPropertyManager->save();


         $this->bossProperties[strtolower($bossName)] = $defaultArray;
    }

    /**
     * @param string $bossName
     * @param bool $isBaby
     * @return array
     */
    public function getDrops(string $bossName,  bool $isBaby = false) : array{
        $properties = $this->bossProperties[strtolower($bossName)];
        $drops = [];
        $dropArray = $properties['drops'][$isBaby ? 'baby' : 'adult'];
        foreach($dropArray as $drop){
            $item = Item::get($drop['id'], $drop['damage'], $drop['count']);

            if(!empty($drop['enchantments'])){
                //TODO: update enchantments
            }

            $customName = $drop['tags']['name'];
            if($customName !== ''){
                $item->setCustomName($customName);
            }

            $lore = $drop['tags']['lore'];
            if($lore !== ''){
                $lore = explode("{LINE}", $lore);
                $item->setLore($lore);
            }

            $drops[] = $item;
        }

        return $drops;

    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onInteract(PlayerInteractEvent $event) : void{
        $item = $event->getItem();
        $block = $event->getBlock();
        if($item instanceof SpawnEgg){
            if(isset(self::EGG_LIST[$item->getDamage()])){
                $bossName = self::EGG_LIST[$item->getDamage()];

                $nbt = Entity::createBaseNBT(new Vector3($block->getX(), $block->getY() + 1.5, $block->getZ()));;
                $bossEntity = Entity::createEntity($bossName . "Boss", $event->getPlayer()->level, $nbt);


                $bossEntity->spawnToAll();
                if($bossEntity instanceof ZeusBoss){
                    $bossEntity->setAnAdult();
                }

                $this->registredEntities[$bossEntity->getId()] = $bossEntity;

            }
        }
    }

    /**
     * @param EntitySpawnEvent $event
     */
    public function onEntitySpawn(EntitySpawnEvent $event) : void{
        $entity = $event->getEntity();
        $classInterfaces = class_implements($entity);
        if(isset($classInterfaces[BossInterface::class])){
            $this->registredEntities[$entity->getId()] = $entity;
        }
    }

    /**
     * @param EntityDespawnEvent $event
     */
    public function onEntityDespawn(EntityDespawnEvent $event) : void{
         $entity = $event->getEntity();
         $classInterfaces = class_implements($entity);
         if(isset($classInterfaces[BossInterface::class])){
             unset($this->registredEntities[$entity->getId()]);
         }
    }

    /**
     * @param string $fileName
     * @return Skin|null
     */
    public function getSkin(string $fileName) : ?Skin{
        $path = $this->getDataFolder()  . "skins" . DIRECTORY_SEPARATOR .  $fileName . ".png";

        if(!is_file($path)){
            return null;
        }

        $img = @imagecreatefrompng($path);
        $bytes = '';
        $l = (int) @getimagesize($path)[1];
        for ($y = 0; $y < $l; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $rgba = @imagecolorat($img, $x, $y);
                $a = ((~((int)($rgba >> 24))) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }

        @imagedestroy($img);

        return new Skin("Standard_CustomSlim", $bytes);

    }

    public function onDisable() : void
    {
        foreach($this->registredEntities as $entity){
            $entity->flagForDespawn();
        }
    }

    /**
     * @param int $health
     * @param int $maxHealth
     * @param int $intoLines
     * @return string
     */
    public function calculateNametag(int $health, int $maxHealth, int $intoLines = 2) : string{
        $perLine = ($maxHealth / $intoLines) / 10;

        $baseText = TextFormat::GRAY . "[" . str_repeat(TextFormat::GREEN . "|", $perLine) . TextFormat::GRAY . "]" . PHP_EOL;
        $baseText = str_repeat($baseText, $intoLines);

        $healthToRemove = (int) ($maxHealth - $health);

        if($healthToRemove < 10){
            return $baseText;
        }

        foreach(range(0, round($healthToRemove / 10)) as $int) {
            $baseText = strrev(implode(strrev(TextFormat::RED), explode(strrev(TextFormat::GREEN), strrev($baseText), 2)));
        }
        return $baseText;


    }



}