<?php

#______           _    _____           _                  
#|  _  \         | |  /  ___|         | |                 
#| | | |__ _ _ __| | _\ `--. _   _ ___| |_ ___ _ __ ___   
#| | | / _` | '__| |/ /`--. \ | | / __| __/ _ \ '_ ` _ \  
#| |/ / (_| | |  |   </\__/ / |_| \__ \ ||  __/ | | | | | 
#|___/ \__,_|_|  |_|\_\____/ \__, |___/\__\___|_| |_| |_| 
#                             __/ |                       
#                            |___/

namespace pocketmine\entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item as ItemItem;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;

class Guardian extends WaterAnimal implements Ageable
{
    const NETWORK_ID = 49;

    public $width = 0.95;
    public $length = 0.95;
    public $height = 0.95;

    public $dropExp = [5, 10];
    
    public $swimDirection = null;
    public $swimSpeed = 0.1;

    private $switchDirectionTicker = 0;

    public function initEntity()
    {
        parent::initEntity();
        
        $this->setMaxHealth(30);
    }

    public function getName()
    {
        return "Guardian";
    }

    public function attack($damage, EntityDamageEvent $source)
    {
        parent::attack($damage, $source);
        if ($source->isCancelled()) {
            return;
        }

        if ($source instanceof EntityDamageByEntityEvent) {
            $this->swimSpeed = mt_rand(150, 350) / 2000;
            $e = $source->getDamager();
            $this->swimDirection = (new Vector3($this->x - $e->x, $this->y - $e->y, $this->z - $e->z))->normalize();

            $pk = new EntityEventPacket();
            $pk->eid = $this->getId();
            $this->server->broadcastPacket($this->hasSpawned, $pk);
        }
    }

    private function generateRandomDirection()
    {
        return new Vector3(mt_rand(-1000, 1000) / 1000, mt_rand(-500, 500) / 1000, mt_rand(-1000, 1000) / 1000);
    }
    
    public function onUpdate($currentTick)
    {
        if ($this->closed !== false) {
            return false;
        }

        if (++$this->switchDirectionTicker === 100) {
            $this->switchDirectionTicker = 0;
            if (mt_rand(0, 100) < 50) {
                $this->swimDirection = null;
            }
        }

        $this->lastUpdate = $currentTick;

        $this->timings->startTiming();

        $hasUpdate = parent::onUpdate($currentTick);

        if ($this->isAlive()) {

            if ($this->y > 62 and $this->swimDirection !== null) {
                $this->swimDirection->y = -0.5;
            }

            $inWater = $this->isInsideOfWater();
            if (!$inWater) {
                $this->motionY -= $this->gravity;
                $this->swimDirection = null;
            } elseif ($this->swimDirection !== null) {
                if ($this->motionX ** 2 + $this->motionY ** 2 + $this->motionZ ** 2 <= $this->swimDirection->lengthSquared()) {
                    $this->motionX = $this->swimDirection->x * $this->swimSpeed;
                    $this->motionY = $this->swimDirection->y * $this->swimSpeed;
                    $this->motionZ = $this->swimDirection->z * $this->swimSpeed;
                }
            } else {
                $this->swimDirection = $this->generateRandomDirection();
                $this->swimSpeed = mt_rand(50, 100) / 2000;
            }

            $expectedPos = new Vector3($this->x + $this->motionX, $this->y + $this->motionY, $this->z + $this->motionZ);

            $this->move($this->motionX, $this->motionY, $this->motionZ);

            if ($expectedPos->distanceSquared($this) > 0) {
                $this->swimDirection = $this->generateRandomDirection();
                $this->swimSpeed = mt_rand(50, 100) / 2000;
            }

            $friction = 1 - $this->drag;

            $this->motionX *= $friction;
            $this->motionY *= 1 - $this->drag;
            $this->motionZ *= $friction;

            $f = sqrt(($this->motionX ** 2) + ($this->motionZ ** 2));
            $this->yaw = (-atan2($this->motionX, $this->motionZ) * 180 / M_PI);
            $this->pitch = (-atan2($f, $this->motionY) * 180 / M_PI);

            if ($this->onGround) {
                $this->motionY *= -0.5;
            }

        }

        $this->timings->stopTiming();

        return $hasUpdate or !$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001;
    }


    public function spawnTo(Player $player)
    {
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = Guardian::NETWORK_ID;
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->speedX = $this->motionX;
        $pk->speedY = $this->motionY;
        $pk->speedZ = $this->motionZ;
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->metadata = $this->dataProperties;
        $player->dataPacket($pk);

        parent::spawnTo($player);
    }

    public function getDrops()
    {
        $drops = array(ItemItem::get(ItemItem::DIAMOND, 0, mt_rand(1, 2)));
        $drops[] = ItemItem::get(ItemItem::RAW_FISH, 0, mt_rand(0, 1));
        return $drops;
    }
}
