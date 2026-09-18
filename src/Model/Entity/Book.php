<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $title
 * @property string $author
 * @property float $price
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class Book extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
