<?php

namespace milk\pureentities\entity;

use pocketmine\entity\Creature;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;

abstract class EntityBase extends Creature{

    protected $speed = 1;

    protected $stayTime = 0;
    protected $moveTime = 0;

    /** @var Vector3|Entity */
    protected $target = \null;

    /** @var Vector3|Entity */
    protected $followTarget = \null;

    private $movement = \true;
    private $friendly = \false;
    private $wallcheck = \true;

    public function __destruct(){}

    public abstract function updateMove($tickDiff);

    public abstract function targetOption(Creature $creature, $distance);

    public function getSaveId(){
        $class = new \ReflectionClass(get_class($this));
        return $class->getShortName();
    }

    public function isMovement(){
        return $this->movement;
    }

    public function isFriendly(){
        return $this->friendly;
    }

    public function isWallCheck(){
        return $this->wallcheck;
    }

    public function setMovement($value){
        $this->movement = $value;
    }

    public function setFriendly($bool){
        $this->friendly = $bool;
    }

    public function setWallCheck($value){
        $this->wallcheck = $value;
    }

    public function getSpeed(){
        return $this->speed;
    }

    public function getTarget(){
        return $this->followTarget != null ? $this->followTarget : ($this->target instanceof Entity ? $this->target : null);
    }

    public function setTarget(Entity $target){
        $this->followTarget = $target;
        
        $this->moveTime = 0;
        $this->stayTime = 0;
        $this->target = \null;
    }
    
    public function initEntity(){
        parent::initEntity();

        if(isset($this->namedtag->Movement)){
            $this->setMovement($this->namedtag['Movement']);
        }
        if(isset($this->namedtag->Friendly)){
            $this->setFriendly($this->namedtag['Friendly']);
        }
        if(isset($this->namedtag->WallCheck)){
            $this->setWallCheck($this->namedtag['WallCheck']);
        }
        $this->setImmobile(\true);
    }

    public function saveNBT(){
        parent::saveNBT();
        $this->namedtag->Movement = new ByteTag('Movement', $this->isMovement());
        $this->namedtag->Friendly = new ByteTag('Friendly', $this->isFriendly());
        $this->namedtag->WallCheck = new ByteTag('WallCheck', $this->isWallCheck());
    }

    public function updateMovement(){
        if($this->lastX !== $this->x){
            $this->lastX = $this->x;
        }

        if($this->lastY !== $this->y){
            $this->lastY = $this->y;
        }

        if($this->lastZ !== $this->z){
            $this->lastZ = $this->z;
        }

        if($this->lastYaw !== $this->yaw){
            $this->lastYaw = $this->yaw;
        }

        if($this->lastPitch !== $this->pitch){
            $this->lastPitch = $this->pitch;
        }
        $this->broadcastMovement();
    }

    public function attack(EntityDamageEvent $source){
        if($this->attackTime > 0) return;

        parent::attack($source);

        if($source->isCancelled() || !($source instanceof EntityDamageByEntityEvent)){
            return;
        }

        $this->stayTime = 0;
        $this->moveTime = 0;

        $damager = $source->getDamager();
        $motion = (new Vector3($this->x - $damager->x, $this->y - $damager->y, $this->z - $damager->z))->normalize();
        $this->motionX = $motion->x * 0.19;
        $this->motionZ = $motion->z * 0.19;
        if($this instanceof FlyingEntity){
            $this->motionY = $motion->y * 0.19;
        }else{
            $this->motionY = 0.6;
        }
    }

    public function knockBack(Entity $attacker, float $damage, float $x, float $z, float $base = 0.4){

    }

    public function entityBaseTick(int $tickDiff = 1) : bool{
        $hasUpdate = Entity::entityBaseTick($tickDiff);

        if($this->isInsideOfSolid()){
            $hasUpdate = \true;
            $ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1);
            $this->attack($ev);
        }

        if($this->moveTime > 0){
            $this->moveTime -= $tickDiff;
        }
        if($this->attackTime > 0){
            $this->attackTime -= $tickDiff;
        }
        return $hasUpdate;
    }

    public function move(float $dx, float $dy, float $dz) : bool{
        $movX = $dx;
        $movY = $dy;
        $movZ = $dz;

        $list = $this->level->getCollisionCubes($this, $this->level->getTickRate() > 1 ? $this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz) : $this->boundingBox->addCoord($dx, $dy, $dz));
        foreach($list as $bb){
            if($this->isWallCheck()){
                $dx = $bb->calculateXOffset($this->boundingBox, $dx);
                $dz = $bb->calculateZOffset($this->boundingBox, $dz);
            }
            $dy = $bb->calculateYOffset($this->boundingBox, $dy);
        }
        $this->boundingBox->offset($dx, $dy, $dz);

        $this->x += $dx;
        $this->y += $dy;
        $this->z += $dz;

        $this->checkChunks();
        $this->checkBlockCollision();
        $this->checkGroundState($movX, $movY, $movZ, $dx, $dy, $dz);
        $this->updateFallState($dy, $this->onGround);
        return \true;
    }

}